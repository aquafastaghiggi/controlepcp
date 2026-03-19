(function () {
    const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    const textDecoder = new TextDecoder('utf-8');

    function readUInt16LE(bytes, offset) {
        return bytes[offset] | (bytes[offset + 1] << 8);
    }

    function readUInt32LE(bytes, offset) {
        return (bytes[offset]) | (bytes[offset + 1] << 8) | (bytes[offset + 2] << 16) | (bytes[offset + 3] << 24);
    }

    function decodeText(bytes) {
        return textDecoder.decode(bytes);
    }

    function parseXml(text) {
        const document = new DOMParser().parseFromString(text, 'application/xml');
        if (document.querySelector('parsererror')) {
            throw new Error('Nao foi possivel ler a planilha enviada.');
        }

        return document;
    }

    async function inflateRaw(bytes) {
        if (typeof DecompressionStream === 'undefined') {
            throw new Error('Seu navegador nao suporta a leitura direta de arquivos .xlsx.');
        }

        const stream = new Blob([bytes]).stream().pipeThrough(new DecompressionStream('deflate-raw'));
        const buffer = await new Response(stream).arrayBuffer();
        return new Uint8Array(buffer);
    }

    async function unzipEntries(arrayBuffer) {
        const bytes = new Uint8Array(arrayBuffer);
        let eocdOffset = -1;

        for (let index = bytes.length - 22; index >= Math.max(0, bytes.length - 65557); index -= 1) {
            if (readUInt32LE(bytes, index) === 0x06054b50) {
                eocdOffset = index;
                break;
            }
        }

        if (eocdOffset === -1) {
            throw new Error('Arquivo Excel invalido.');
        }

        const centralDirectoryOffset = readUInt32LE(bytes, eocdOffset + 16);
        const entriesCount = readUInt16LE(bytes, eocdOffset + 10);
        const entries = new Map();
        let cursor = centralDirectoryOffset;

        for (let entryIndex = 0; entryIndex < entriesCount; entryIndex += 1) {
            if (readUInt32LE(bytes, cursor) !== 0x02014b50) {
                throw new Error('Estrutura ZIP invalida no arquivo Excel.');
            }

            const compressionMethod = readUInt16LE(bytes, cursor + 10);
            const compressedSize = readUInt32LE(bytes, cursor + 20);
            const fileNameLength = readUInt16LE(bytes, cursor + 28);
            const extraLength = readUInt16LE(bytes, cursor + 30);
            const commentLength = readUInt16LE(bytes, cursor + 32);
            const localHeaderOffset = readUInt32LE(bytes, cursor + 42);
            const filenameBytes = bytes.slice(cursor + 46, cursor + 46 + fileNameLength);
            const filename = decodeText(filenameBytes);

            if (readUInt32LE(bytes, localHeaderOffset) !== 0x04034b50) {
                throw new Error('Cabecalho local invalido no arquivo Excel.');
            }

            const localFileNameLength = readUInt16LE(bytes, localHeaderOffset + 26);
            const localExtraLength = readUInt16LE(bytes, localHeaderOffset + 28);
            const dataOffset = localHeaderOffset + 30 + localFileNameLength + localExtraLength;
            const compressedData = bytes.slice(dataOffset, dataOffset + compressedSize);

            let fileData;
            if (compressionMethod === 0) {
                fileData = compressedData;
            } else if (compressionMethod === 8) {
                fileData = await inflateRaw(compressedData);
            } else {
                throw new Error('O arquivo Excel usa uma compactacao nao suportada.');
            }

            entries.set(filename, fileData);
            cursor += 46 + fileNameLength + extraLength + commentLength;
        }

        return entries;
    }

    function getXmlEntry(entries, path) {
        const bytes = entries.get(path);
        return bytes ? parseXml(decodeText(bytes)) : null;
    }

    function resolveSheetInfos(entries) {
        const workbook = getXmlEntry(entries, 'xl/workbook.xml');
        const relationships = getXmlEntry(entries, 'xl/_rels/workbook.xml.rels');

        if (!workbook || !relationships) {
            return [{ name: 'Planilha 1', path: 'xl/worksheets/sheet1.xml' }];
        }

        const relationshipMap = new Map(
            [...relationships.getElementsByTagNameNS('*', 'Relationship')].map((relationship) => [
                relationship.getAttribute('Id'),
                relationship.getAttribute('Target') || '',
            ]),
        );

        const infos = [...workbook.getElementsByTagNameNS('*', 'sheet')].map((sheet) => {
            const relationshipId = sheet.getAttribute('r:id') || sheet.getAttributeNS(REL_NS, 'id');
            const target = relationshipMap.get(relationshipId) || '';
            if (!target) {
                return null;
            }

            return {
                name: sheet.getAttribute('name') || 'Planilha',
                path: target.startsWith('/') ? target.slice(1) : `xl/${target.replace(/^\//, '')}`,
            };
        }).filter(Boolean);

        return infos.length ? infos : [{ name: 'Planilha 1', path: 'xl/worksheets/sheet1.xml' }];
    }

    function readSharedStrings(entries) {
        const document = getXmlEntry(entries, 'xl/sharedStrings.xml');
        if (!document) {
            return [];
        }

        return [...document.getElementsByTagNameNS('*', 'si')].map((item) => item.textContent || '');
    }

    function columnToIndex(reference) {
        const letters = String(reference || '').replace(/\d+/g, '').toUpperCase();
        let index = 0;

        for (let position = 0; position < letters.length; position += 1) {
            index = (index * 26) + (letters.charCodeAt(position) - 64);
        }

        return Math.max(index - 1, 0);
    }

    function readSheetRows(entries, path, sharedStrings) {
        const document = getXmlEntry(entries, path);
        if (!document) {
            throw new Error('Nao foi possivel localizar a planilha no arquivo.');
        }

        const rows = [];
        for (const row of document.getElementsByTagNameNS('*', 'row')) {
            const values = {};
            for (const cell of row.getElementsByTagNameNS('*', 'c')) {
                const cellIndex = columnToIndex(cell.getAttribute('r'));
                const type = cell.getAttribute('t') || '';
                let value = '';

                if (type === 'inlineStr') {
                    value = cell.getElementsByTagNameNS('*', 'is')[0]?.textContent || '';
                } else {
                    value = cell.getElementsByTagNameNS('*', 'v')[0]?.textContent || '';
                    if (type === 's') {
                        value = sharedStrings[Number(value)] || '';
                    }
                }

                values[cellIndex] = String(value).trim();
            }

            if (Object.keys(values).length) {
                rows.push(values);
            }
        }

        return rows;
    }

    function normalizeHeader(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    function normalizeProductName(value) {
        const text = String(value || '').replace(/\s+/g, ' ').trim();

        // Remove trecho de embalagem do tipo "Cx/ 04 X 3L", "cx 04 x 3l", etc.,
        // preservando a parte da litragem (ex.: "3L").
        const match = text.match(/^(.*?)(?:\s+cx\s*\/?\s*[^\s]+\s*x\s+)(.+)$/i);

        if (!match) {
            return text;
        }

        return (match[1].trim() + ' ' + match[2].trim())
            .replace(/\s+/g, ' ')
            .trim();
    }

    function matchesAny(value, candidates) {
        return candidates.some((candidate) => value === candidate || value.includes(candidate));
    }

    function detectColumns(headerRow) {
        const columns = {
            code: null,
            description: null,
            line: null,
            ratePerHour: null,
        };

        Object.entries(headerRow).forEach(([index, label]) => {
            const normalized = normalizeHeader(label);

            if (columns.code === null && matchesAny(normalized, ['cod', 'codigo', 'cod produto', 'codigo produto'])) {
                columns.code = Number(index);
                return;
            }

            if (columns.description === null && matchesAny(normalized, ['descricao', 'descricao produto', 'produto', 'nome produto', 'sku'])) {
                columns.description = Number(index);
                return;
            }

            if (columns.line === null && matchesAny(normalized, ['linha', 'linha producao', 'linha de producao'])) {
                columns.line = Number(index);
                return;
            }

            if (columns.ratePerHour === null && matchesAny(normalized, ['rendimento', 'producao', 'producao hora', 'producao h', 'producao por hora', 'taxa', 'rendim'])) {
                columns.ratePerHour = Number(index);
            }
        });

        return columns;
    }

    function detectMatrixColumns(headerRow) {
        const columns = {
            from: 0,
            to: 1,
            duration: 2,
        };

        Object.entries(headerRow || {}).forEach(([index, label]) => {
            const normalized = normalizeHeader(label);

            if (matchesAny(normalized, ['origem', 'produto origem', 'sku origem', 'from'])) {
                columns.from = Number(index);
                return;
            }

            if (matchesAny(normalized, ['destino', 'produto destino', 'sku destino', 'to'])) {
                columns.to = Number(index);
                return;
            }

            if (matchesAny(normalized, ['tempo', 'tempo setup', 'setup', 'tempo de setup', 'duracao', 'duration', 'minutos'])) {
                columns.duration = Number(index);
            }
        });

        return columns;
    }

    function isMatrixHeaderRow(row) {
        const cells = Object.values(row || {}).map((value) => normalizeHeader(String(value || ''))).filter(Boolean);
        if (!cells.length) {
            return false;
        }

        const hasOrigin = cells.some((cell) => matchesAny(cell, ['origem', 'produto origem', 'sku origem', 'from']));
        const hasDestination = cells.some((cell) => matchesAny(cell, ['destino', 'produto destino', 'sku destino', 'to']));
        const hasDuration = cells.some((cell) => matchesAny(cell, ['tempo', 'tempo setup', 'setup', 'tempo de setup', 'duracao', 'duration', 'minutos']));

        // Consider it a header when it clearly contains column labels
        return (hasOrigin && hasDestination) || (hasDuration && (hasOrigin || hasDestination));
    }

    function parseNumber(value) {
        let normalized = String(value || '').trim();
        if (!normalized) {
            return null;
        }

        normalized = normalized.replace(/\s+/g, '');

        if (/^\d{1,3}(\.\d{3})*,\d+$/.test(normalized)) {
            normalized = normalized.replace(/\./g, '').replace(',', '.');
        } else if (normalized.includes(',') && !normalized.includes('.')) {
            normalized = normalized.replace(',', '.');
        } else {
            normalized = normalized.replace(/,/g, '');
        }

        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function normalizeMatrixLineLabel(value) {
        const text = String(value || '').trim();
        const digits = text.replace(/\D+/g, '');
        if (!digits) {
            return text.toUpperCase() || 'SEM LINHA';
        }

        return `LINHA ${digits.padStart(2, '0')}`;
    }

    function normalizeSku(value) {
        const text = String(value || '').trim();
        if (!text) {
            return '';
        }

        const normalizedWhitespace = text.replace(/\s+/g, ' ');

        // If it looks like a number (possibly scientific notation), normalize to plain integer string.
        if (/^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/.test(normalizedWhitespace)) {
            const num = Number(normalizedWhitespace);
            if (Number.isFinite(num)) {
                return String(Math.trunc(num));
            }
        }

        return normalizedWhitespace;
    }

    function getFilledIndexes(row) {
        return Object.keys(row || {})
            .map(Number)
            .filter((index) => String(row[index] || '').trim() !== '')
            .sort((left, right) => left - right);
    }

    function buildMatrixBlocks(row, lineIndex = -1) {
        const indexes = getFilledIndexes(row).filter((index) => index !== lineIndex);
        const groups = [];
        let currentGroup = [];

        indexes.forEach((index) => {
            if (!currentGroup.length || index === currentGroup[currentGroup.length - 1] + 1) {
                currentGroup.push(index);
                return;
            }

            groups.push(currentGroup);
            currentGroup = [index];
        });

        if (currentGroup.length) {
            groups.push(currentGroup);
        }

        return groups
            .map((group) => {
                const headerStart = group[0];
                return {
                    originIndex: headerStart - 1,
                    headerStart,
                    headers: group.map((index) => String(row[index] || '').trim()),
                };
            })
            .filter((block) => block.headers.some(Boolean));
    }

    function formatDurationFromExcel(value) {
        const text = String(value || '').trim();
        
        // Check if already in HH:MM format
        if (/^\d{1,2}:\d{2}$/.test(text)) {
            return text;
        }

        const numeric = parseNumber(value);
        if (numeric === null) {
            return '';
        }

        const totalMinutes = Math.max(0, Math.round(numeric * 24 * 60));
        const hours = String(Math.floor(totalMinutes / 60)).padStart(2, '0');
        const minutes = String(totalMinutes % 60).padStart(2, '0');
        return `${hours}:${minutes}`;
    }

    async function parseProducts(file, defaultLine = 'L2') {
        const entries = await unzipEntries(await file.arrayBuffer());
        const sharedStrings = readSharedStrings(entries);
        const sheetInfos = resolveSheetInfos(entries);
        const rows = readSheetRows(entries, sheetInfos[0].path, sharedStrings);

        if (!rows.length) {
            throw new Error('A planilha enviada nao possui dados validos.');
        }

        const headerRow = rows.shift();
        const columns = detectColumns(headerRow);

        if (columns.code === null || columns.description === null || columns.ratePerHour === null) {
            throw new Error('Nao foi possivel localizar as colunas de codigo, descricao e rendimento.');
        }

        const products = {};
        rows.forEach((row) => {
            const sku = String(row[columns.code] || '').trim();
            const description = normalizeProductName(row[columns.description]);
            const ratePerHour = parseNumber(row[columns.ratePerHour]);
            const line = columns.line !== null ? String(row[columns.line] || '').trim() : '';

            if (!sku || !description || ratePerHour === null) {
                return;
            }

            products[sku] = {
                description,
                reference_setup: description,
                line: line || defaultLine,
                rate_per_hour: ratePerHour,
                unit: 'cx',
            };
        });

        if (!Object.keys(products).length) {
            throw new Error('Nenhum produto valido foi encontrado no arquivo.');
        }

        return {
            products,
            count: Object.keys(products).length,
        };
    }

    async function parseMatrix(file) {
        const entries = await unzipEntries(await file.arrayBuffer());
        const sharedStrings = readSharedStrings(entries);
        const sheetInfos = resolveSheetInfos(entries);
        const matrixRows = [];
        const sheetSummaries = [];

        sheetInfos.forEach((sheetInfo, sheetIndex) => {
            const sheetLabel = String(sheetInfo.name || '').trim() || `Planilha ${sheetIndex + 1}`;
            console.log("[MatrixImport] Reading sheet", sheetLabel || sheetInfo.path);
            const rows = readSheetRows(entries, sheetInfo.path, sharedStrings);
            const currentLine = normalizeMatrixLineLabel(sheetLabel || '');
            const headerRow = rows[0] || {};
            const columns = detectMatrixColumns(headerRow);
            const hasExplicitColumns = isMatrixHeaderRow(headerRow);
            const dataRows = hasExplicitColumns ? rows.slice(1) : rows;
            let sheetRowCount = 0;

            dataRows.forEach((row) => {
                const originRaw = String(row[columns.from] ?? '').trim();
                let destinationRaw = String(row[columns.to] ?? '').trim();
                const durationRaw = String(row[columns.duration] ?? '').trim();

                const concatenated = String(row[0] ?? '').trim();
                const timeValue = durationRaw || String(row[2] || row[1] || '').trim();

                const destinationLooksLikeDuration = /^\d{1,2}:\d{2}(?::\d{2})?$/.test(destinationRaw) || parseNumber(destinationRaw) !== null;

                const originLooksLikeConcatenatedSkus = /\s+/.test(originRaw);

                if (originLooksLikeConcatenatedSkus && destinationLooksLikeDuration) {
                    destinationRaw = '';
                }

                if ((!originRaw || !destinationRaw) && (!concatenated || !timeValue)) {
                    return;
                }

                let origin = normalizeSku(originRaw);
                let destination = normalizeSku(destinationRaw);

                if (!origin || !destination) {
                    const separatorMatch = concatenated.split(/\s*(?:->|=>|;|\||\/)\s*/).filter(Boolean);
                    if (separatorMatch.length >= 2) {
                        origin = normalizeSku(separatorMatch[0]);
                        destination = normalizeSku(separatorMatch[1]);
                    } else {
                        const skuParts = concatenated.split(/\s+/).filter(Boolean);
                        if (skuParts.length < 2) {
                            return;
                        }
                        origin = normalizeSku(skuParts[0]);
                        destination = normalizeSku(skuParts[1]);
                    }
                }

                let duration = timeValue;
                if (/^\d{1,2}:\d{2}:\d{2}$/.test(duration)) {
                    duration = duration.substring(0, 5);
                } else {
                    duration = formatDurationFromExcel(timeValue);
                }

                if (!duration) {
                    return;
                }

                matrixRows.push({
                    line: currentLine || 'SEM LINHA',
                    from: origin,
                    to: destination,
                    duration,
                });
                sheetRowCount += 1;
            });

            sheetSummaries.push({
                sheetName: sheetLabel,
                lineLabel: currentLine || 'SEM LINHA',
                count: sheetRowCount,
            });
        });

        if (!matrixRows.length) {
            throw new Error('Nenhum tempo de setup valido foi encontrado na planilha.');
        }

        console.log("[MatrixImport] Matrix rows ready for save", matrixRows);

        return {
            rows: matrixRows,
            count: matrixRows.length,
            sheetSummaries,
        };
    }

    window.PCPXlsxImport = {
        parseProducts,
        parseMatrix,
    };
}());








const fs = require('fs');
const path = require('path');
const file = path.join('assets', 'js', 'app.js');
let text = fs.readFileSync(file, 'utf8');
const pipe = String.fromCharCode(124);
const target = [
      const lineLabel = escapeHtml(data.lin_nome  data.lin_codigo  'Linha não informada');,
      const loteLabel = escapeHtml(data.prg_id  prgId);,
      const baseInicio = safeCell(data.prg_base_inicio);,
].join('\r\n');
const replacement = [
      const lineLabel = escapeHtml(data.lin_nome  data.lin_codigo  'Linha não informada');,
      const loteLabel = escapeHtml(data.prg_id  prgId);,
  '',
  '    const formatDateTimeBR = (dataStr, horaStr) => {',
  '      if (!dataStr) return \'\';',
  '      const partes = String(dataStr).split('-');',
  '      if (partes.length !== 3) return \'\';',
        const dataFormatada = \${partes[2]}//\;,
  '      if (!horaStr) {',
  '        return dataFormatada;',
  '      }',
  '      const hora = String(horaStr).slice(0, 5);',
        return \${dataFormatada} \;,
  '    };',
  '',
  '    const firstScheduleItem = schedule.find((entry) => entry && entry.sch_data_inicio);',
  '    baseInicio = formatDateTimeBR(',
  '      firstScheduleItem?.sch_data_inicio,',
  '      firstScheduleItem?.sch_inicio ?? firstScheduleItem?.sch_hora_inicio',
  '    ) || baseInicio;',
].join('\r\n');
if (!text.includes(target)) {
  throw new Error('target not found');
}
text = text.replace(target, replacement);
fs.writeFileSync(file, text, 'utf8');

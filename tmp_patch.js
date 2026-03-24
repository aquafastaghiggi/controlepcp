const fs = require('fs');
const path = require('path');
const file = path.join('assets', 'js', 'app.js');
let lines = fs.readFileSync(file, 'utf8').split(/\r?\n/);
const baseIndex = lines.findIndex((line) => line.includes('const baseInicio = safeCell'));
if (baseIndex === -1) {
  throw new Error('baseInicio line not found');
}
lines[baseIndex] = '    const safeBaseInicio = safeCell(data.prg_base_inicio);';
const insertion = [
  '    const formatDateTimeBR = (dateStr, timeStr) => {',
  '      if (!dateStr) return '';',
  '      const parts = String(dateStr).split('-');',
  '      if (parts.length !== 3) return '';',
  '      const formattedDate = ${parts[2]}//;',
  '      if (!timeStr) return formattedDate;',
  '      const formattedTime = String(timeStr).slice(0, 5);',
  '      return ${formattedDate} ;',
  '    };',
  '    const firstScheduleItem = schedule.find((entry) => entry && entry.sch_data_inicio);',
  '    const baseInicio = formatDateTimeBR(',
  '      firstScheduleItem?.sch_data_inicio,',
  '      firstScheduleItem?.sch_hora_inicio',
  '    ) || safeBaseInicio;',
].join('\r\n');
lines.splice(baseIndex + 1, 0, insertion);
fs.writeFileSync(file, lines.join('\r\n'), 'utf8');

 = 'assets/js/app.js'
 = Get-Content -Encoding UTF8 
 = -1
for ( = 0;  -lt .Length; ++) {
  if ([] -like '*const lineLabel = escapeHtml(data.lin_nome*') {
     = 
    break
  }
}
if ( -lt 0) { throw 'lineLabel not found' }
[ + 2] = '    let baseInicio = safeCell(data.prg_base_inicio);'
 = @(
  '',
  '    const formatDateTimeBR = (dataStr, horaStr) => {',
  '      if (!dataStr) return '';',
  '      const partes = String(dataStr).split('-');',
  '      if (partes.length !== 3) return '';',
  '      const dataFormatada = ${partes[2]}//;',
  '      if (!horaStr) {',
  '        return dataFormatada;',
  '      }',
  '      const hora = String(horaStr).slice(0, 5);',
  '      return ${dataFormatada} ;',
  '    };',
  '',
  '    const firstScheduleItem = schedule.find((entry) => entry && entry.sch_data_inicio);',
  '    baseInicio = formatDateTimeBR(',
  '      firstScheduleItem?.sch_data_inicio,',
  '      firstScheduleItem?.sch_inicio ?? firstScheduleItem?.sch_hora_inicio',
  '    ) || baseInicio;',
)
 = [0..( + 2)]
 = if ( + 3 -lt .Length) { [( + 3)..(.Length - 1)] } else { @() }
 =  +  + 
Set-Content -Encoding UTF8  -Value 

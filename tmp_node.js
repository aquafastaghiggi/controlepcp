const fs = require('fs');
const path = 'assets/js/app.js';
let content = fs.readFileSync(path, 'utf8');

const weekDaysMarker = '    const weekDays = [ Dom, Seg, Ter, Qua, Qui, Sex, Sáb];';
const helper = '    const formatDateTimeBR = (dateStr, timeStr) => {\r\n      if (!dateStr) {\r\n        return ;\r\n }\r\n\r\n const partes = String(dateStr).split( -);\r\n if (partes.length !== 3) {\r\n return ;\r\n      }\r\n\r\n      const formattedDate = partes[2] + / + partes[1] + / + partes[0];\r\n      if (!timeStr) {\r\n        return formattedDate;\r\n      }\r\n\r\n      const formattedTime = String(timeStr).substring(0, 5);\r\n      return formattedDate +   + formattedTime;\r\n    };\r\n\r\n';
if (!content.includes(weekDaysMarker)) { throw 'weekDays marker not found'; }
content = content.replace(weekDaysMarker, helper + weekDaysMarker);


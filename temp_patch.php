<?php
$path='assets/js/app.js';
$text=file_get_contents($path);
$old="    function readProgramRows() {\r\n        return [...programBody.querySelectorAll('[tr]')].map((row, index) => ({\r\n            sequence: Number(row.querySelector('[name=\"sequence\"]').value) || index + 1,\r\n            op: row.querySelector('[name=\"op\"]').value || '',\r\n            sku: row.querySelector('[name=\"sku\"]').value,\r\n            quantity: Number(row.querySelector('[name=\"quantity\"]').value) || 0,\r\n            planned_start: index === 0 ? row.querySelector('[name=\"planned_start\"]').value : '',\r\n        }));\r\n    }";
?>

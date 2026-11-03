// Export: 'Nodig' en 'Gepicked' zijn aparte numerieke cellen. Run: node tests/elpis_xlsx_test.mjs [out.xlsx]
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const X = require('../web/elpis_xlsx.js');
const indexPhp = readFileSync(new URL('../web/index.php', import.meta.url), 'utf8');

test('export bevat Nodig en Gepicked als getallen', async () => {
    assert.match(indexPhp, /'received', 'needed', 'picked'/);
    assert.match(indexPhp, /num\(line\.qty_needed\), num\(line\.qty_picked\)/);
    assert.doesNotMatch(indexPhp, /qty_picked_display \|\| ''/);
    const blob = X.buildXlsxBlob(['Artikel', 'Nodig', 'Gepicked'], [['A', 4, 0]], 'T');
    const buf = Buffer.from(await blob.arrayBuffer());
    if (process.argv[2]) writeFileSync(process.argv[2], buf);
    assert.ok(buf.includes('<c r="B2"><v>4</v></c>') && buf.includes('<c r="C2"><v>0</v></c>'));
});

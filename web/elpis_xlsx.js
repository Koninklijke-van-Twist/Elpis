/**
 * Elpis: minimale xlsx-export zonder library (gekopieerd uit Argus maand-detail.js buildXlsxBlob).
 * Gebruik: ElpisXlsx.download('bestand.xlsx', headers, rows, 'Blad').
 */
(function (root) {
    'use strict';
    function crc32 (bytes)
    {
        let crc = 0xffffffff;
        for (let i = 0; i < bytes.length; i++)
        {
            crc ^= bytes[i];
            for (let j = 0; j < 8; j++)
            {
                crc = (crc >>> 1) ^ (0xedb88320 & -(crc & 1));
            }
        }
        return (crc ^ 0xffffffff) >>> 0;
    }

    function u16 (value)
    {
        return new Uint8Array([value & 255, (value >>> 8) & 255]);
    }

    function u32 (value)
    {
        return new Uint8Array([
            value & 255,
            (value >>> 8) & 255,
            (value >>> 16) & 255,
            (value >>> 24) & 255,
        ]);
    }

    function concatBytes (parts)
    {
        let total = 0;
        for (const part of parts)
        {
            total += part.length;
        }
        const out = new Uint8Array(total);
        let offset = 0;
        for (const part of parts)
        {
            out.set(part, offset);
            offset += part.length;
        }
        return out;
    }

    function encodeUtf8 (text)
    {
        return new TextEncoder().encode(String(text));
    }

    function buildZip (files)
    {
        const localParts = [];
        const centralParts = [];
        let offset = 0;

        for (const file of files)
        {
            const nameBytes = encodeUtf8(file.name);
            const dataBytes = file.data instanceof Uint8Array ? file.data : encodeUtf8(file.data);
            const crc = crc32(dataBytes);
            const localHeader = concatBytes([
                u32(0x04034b50),
                u16(20),
                u16(0),
                u16(0),
                u16(0),
                u16(0),
                u32(crc),
                u32(dataBytes.length),
                u32(dataBytes.length),
                u16(nameBytes.length),
                u16(0),
                nameBytes,
            ]);
            localParts.push(localHeader, dataBytes);

            const centralHeader = concatBytes([
                u32(0x02014b50),
                u16(20),
                u16(20),
                u16(0),
                u16(0),
                u16(0),
                u16(0),
                u32(crc),
                u32(dataBytes.length),
                u32(dataBytes.length),
                u16(nameBytes.length),
                u16(0),
                u16(0),
                u16(0),
                u16(0),
                u32(0),
                u32(offset),
                nameBytes,
            ]);
            centralParts.push(centralHeader);
            offset += localHeader.length + dataBytes.length;
        }

        const central = concatBytes(centralParts);
        const end = concatBytes([
            u32(0x06054b50),
            u16(0),
            u16(0),
            u16(files.length),
            u16(files.length),
            u32(central.length),
            u32(offset),
            u16(0),
        ]);

        return concatBytes(localParts.concat([central, end]));
    }

    function excelColName (index)
    {
        let n = index + 1;
        let name = '';
        while (n > 0)
        {
            const rem = (n - 1) % 26;
            name = String.fromCharCode(65 + rem) + name;
            n = Math.floor((n - 1) / 26);
        }
        return name;
    }

    function xmlEscape (value)
    {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function buildSheetCellXml (colIndex, rowIndex, value)
    {
        const ref = excelColName(colIndex) + String(rowIndex);
        if (value && typeof value === 'object' && value.formula)
        {
            const cached = typeof value.value === 'number' && isFinite(value.value) ? value.value : 0;
            return '<c r="' + ref + '"><f>' + xmlEscape(value.formula) + '</f><v>' + cached + '</v></c>';
        }
        if (typeof value === 'number' && isFinite(value))
        {
            return '<c r="' + ref + '"><v>' + value + '</v></c>';
        }
        return '<c r="' + ref + '" t="inlineStr"><is><t>' + xmlEscape(value) + '</t></is></c>';
    }

    function buildXlsxBlob (headers, rows, sheetName)
    {
        const sheetRows = [];
        sheetRows.push('<row r="1">' + headers.map(function (header, colIndex)
        {
            return buildSheetCellXml(colIndex, 1, header);
        }).join('') + '</row>');

        for (let rowIndex = 0; rowIndex < rows.length; rowIndex++)
        {
            const excelRow = rowIndex + 2;
            const row = rows[rowIndex];
            sheetRows.push('<row r="' + excelRow + '">' + row.map(function (value, colIndex)
            {
                return buildSheetCellXml(colIndex, excelRow, value);
            }).join('') + '</row>');
        }

        const sheetXml = [
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">',
            '<sheetData>',
            sheetRows.join(''),
            '</sheetData>',
            '</worksheet>',
        ].join('');

        const files = [
            {
                name: '[Content_Types].xml',
                data: [
                    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
                    '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">',
                    '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>',
                    '<Default Extension="xml" ContentType="application/xml"/>',
                    '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>',
                    '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
                    '</Types>',
                ].join(''),
            },
            {
                name: '_rels/.rels',
                data: [
                    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
                    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">',
                    '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>',
                    '</Relationships>',
                ].join(''),
            },
            {
                name: 'xl/workbook.xml',
                data: [
                    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
                    '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">',
                    '<sheets><sheet name="' + xmlEscape(String(sheetName || 'Elpis').slice(0, 31)) + '" sheetId="1" r:id="rId1"/></sheets>',
                    '</workbook>',
                ].join(''),
            },
            {
                name: 'xl/_rels/workbook.xml.rels',
                data: [
                    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
                    '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">',
                    '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>',
                    '</Relationships>',
                ].join(''),
            },
            {
                name: 'xl/worksheets/sheet1.xml',
                data: sheetXml,
            },
        ];

        return new Blob([buildZip(files)], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
    }

    function download (fileName, headers, rows, sheetName)
    {
        const blob = buildXlsxBlob(headers, rows, sheetName);
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    const api = { buildXlsxBlob: buildXlsxBlob, buildZip: buildZip, download: download };
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.ElpisXlsx = api;
    }
})(typeof window !== 'undefined' ? window : globalThis);

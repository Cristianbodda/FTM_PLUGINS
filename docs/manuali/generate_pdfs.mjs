/**
 * Generatore PDF per Manuali FTM
 *
 * Genera PDF da tutti i file Markdown dei manuali
 */

import { mdToPdf } from 'md-to-pdf';
import { readdir, mkdir, readFile, writeFile } from 'fs/promises';
import { existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const CONFIG = {
    manualsDir: __dirname,
    outputDir: join(__dirname, 'pdf'),

    // Stile PDF
    pdfOptions: {
        stylesheet: [],
        css: `
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                font-size: 11pt;
                line-height: 1.6;
                color: #333;
                max-width: 100%;
            }
            h1 {
                color: #e65100;
                border-bottom: 3px solid #e65100;
                padding-bottom: 10px;
                margin-top: 30px;
            }
            h2 {
                color: #0066cc;
                border-bottom: 1px solid #0066cc;
                padding-bottom: 5px;
                margin-top: 25px;
            }
            h3 {
                color: #333;
                margin-top: 20px;
            }
            h4 {
                color: #666;
                margin-top: 15px;
            }
            table {
                border-collapse: collapse;
                width: 100%;
                margin: 15px 0;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: left;
            }
            th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            tr:nth-child(even) {
                background-color: #fafafa;
            }
            code {
                background-color: #f4f4f4;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: 'Consolas', monospace;
                font-size: 10pt;
            }
            pre {
                background-color: #f4f4f4;
                padding: 15px;
                border-radius: 5px;
                overflow-x: auto;
            }
            pre code {
                padding: 0;
                background: none;
            }
            blockquote {
                border-left: 4px solid #e65100;
                margin: 15px 0;
                padding: 10px 20px;
                background-color: #fff8e1;
            }
            img {
                max-width: 100%;
                height: auto;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin: 10px 0;
            }
            a {
                color: #0066cc;
                text-decoration: none;
            }
            ul, ol {
                margin: 10px 0;
                padding-left: 25px;
            }
            li {
                margin: 5px 0;
            }
            .page-break {
                page-break-after: always;
            }
            @page {
                margin: 2cm;
            }
        `,
        pdf_options: {
            format: 'A4',
            margin: {
                top: '2cm',
                bottom: '2cm',
                left: '2cm',
                right: '2cm'
            },
            printBackground: true,
            displayHeaderFooter: true,
            headerTemplate: '<div style="font-size: 9px; color: #999; width: 100%; text-align: center; padding: 5px;">FTM - Fondazione Terzo Millennio</div>',
            footerTemplate: '<div style="font-size: 9px; color: #999; width: 100%; text-align: center; padding: 5px;"><span class="pageNumber"></span> / <span class="totalPages"></span></div>'
        }
    }
};

async function ensureDir(dir) {
    if (!existsSync(dir)) {
        await mkdir(dir, { recursive: true });
    }
}

async function convertToPdf(inputPath, outputPath, title) {
    console.log(`📄 Convertendo: ${title}`);

    try {
        const pdf = await mdToPdf(
            { path: inputPath },
            {
                ...CONFIG.pdfOptions,
                dest: outputPath
            }
        );

        if (pdf) {
            console.log(`   ✅ Salvato: ${outputPath}`);
            return true;
        }
    } catch (error) {
        console.log(`   ❌ Errore: ${error.message}`);
        return false;
    }
    return false;
}

async function combineMarkdownFiles(files, outputPath, title) {
    console.log(`📚 Combinando ${files.length} file per: ${title}`);

    let combined = `# ${title}\n\n`;
    combined += `**Fondazione Terzo Millennio - Sistema FTM**\n\n`;
    combined += `**Data:** ${new Date().toLocaleDateString('it-IT')}\n\n`;
    combined += `---\n\n`;

    for (const file of files) {
        try {
            let content = await readFile(file, 'utf-8');

            // Rimuovi il frontmatter se presente
            content = content.replace(/^---[\s\S]*?---\n*/m, '');

            // Aggiungi page break tra capitoli
            combined += content + '\n\n<div class="page-break"></div>\n\n';

        } catch (error) {
            console.log(`   ⚠️ Errore leggendo ${file}: ${error.message}`);
        }
    }

    await writeFile(outputPath, combined, 'utf-8');
    console.log(`   ✅ File combinato salvato: ${outputPath}`);
    return outputPath;
}

async function main() {
    console.log('='.repeat(60));
    console.log('FTM - Generazione PDF Manuali');
    console.log('='.repeat(60));

    await ensureDir(CONFIG.outputDir);
    console.log(`\n📁 Output: ${CONFIG.outputDir}\n`);

    let successCount = 0;
    let failCount = 0;

    // ===== PDF SINGOLI =====
    console.log('\n' + '='.repeat(50));
    console.log('📄 GENERAZIONE PDF SINGOLI');
    console.log('='.repeat(50));

    // Guida Rapida
    if (existsSync(join(CONFIG.manualsDir, '00_GUIDA_RAPIDA.md'))) {
        const success = await convertToPdf(
            join(CONFIG.manualsDir, '00_GUIDA_RAPIDA.md'),
            join(CONFIG.outputDir, '00_Guida_Rapida.pdf'),
            'Guida Rapida'
        );
        if (success) successCount++; else failCount++;
    }

    // Troubleshooting
    if (existsSync(join(CONFIG.manualsDir, '99_TROUBLESHOOTING.md'))) {
        const success = await convertToPdf(
            join(CONFIG.manualsDir, '99_TROUBLESHOOTING.md'),
            join(CONFIG.outputDir, '99_Troubleshooting.pdf'),
            'Troubleshooting'
        );
        if (success) successCount++; else failCount++;
    }

    // Manuali Coach singoli
    console.log('\n--- Manuali Coach ---');
    const coachDir = join(CONFIG.manualsDir, 'coach');
    if (existsSync(coachDir)) {
        const coachFiles = (await readdir(coachDir)).filter(f => f.endsWith('.md')).sort();
        for (const file of coachFiles) {
            const success = await convertToPdf(
                join(coachDir, file),
                join(CONFIG.outputDir, `Coach_${file.replace('.md', '.pdf')}`),
                `Coach: ${file}`
            );
            if (success) successCount++; else failCount++;
        }
    }

    // Manuali Segreteria singoli
    console.log('\n--- Manuali Segreteria ---');
    const segreteriaDir = join(CONFIG.manualsDir, 'segreteria');
    if (existsSync(segreteriaDir)) {
        const segreteriaFiles = (await readdir(segreteriaDir)).filter(f => f.endsWith('.md')).sort();
        for (const file of segreteriaFiles) {
            const success = await convertToPdf(
                join(segreteriaDir, file),
                join(CONFIG.outputDir, `Segreteria_${file.replace('.md', '.pdf')}`),
                `Segreteria: ${file}`
            );
            if (success) successCount++; else failCount++;
        }
    }

    // ===== PDF COMBINATI =====
    console.log('\n' + '='.repeat(50));
    console.log('📚 GENERAZIONE PDF COMBINATI');
    console.log('='.repeat(50));

    // Manuale Coach Completo
    console.log('\n--- Manuale Coach Completo ---');
    if (existsSync(coachDir)) {
        const coachFiles = (await readdir(coachDir))
            .filter(f => f.endsWith('.md'))
            .sort()
            .map(f => join(coachDir, f));

        // Aggiungi guida rapida all'inizio
        const guidaRapida = join(CONFIG.manualsDir, '00_GUIDA_RAPIDA.md');
        if (existsSync(guidaRapida)) {
            coachFiles.unshift(guidaRapida);
        }

        const combinedPath = join(CONFIG.outputDir, '_temp_coach_combined.md');
        await combineMarkdownFiles(coachFiles, combinedPath, 'Manuale Coach FTM');

        const success = await convertToPdf(
            combinedPath,
            join(CONFIG.outputDir, 'Manuale_Coach_Completo.pdf'),
            'Manuale Coach Completo'
        );
        if (success) successCount++; else failCount++;
    }

    // Manuale Segreteria Completo
    console.log('\n--- Manuale Segreteria Completo ---');
    if (existsSync(segreteriaDir)) {
        const segreteriaFiles = (await readdir(segreteriaDir))
            .filter(f => f.endsWith('.md'))
            .sort()
            .map(f => join(segreteriaDir, f));

        // Aggiungi guida rapida all'inizio
        const guidaRapida = join(CONFIG.manualsDir, '00_GUIDA_RAPIDA.md');
        if (existsSync(guidaRapida)) {
            segreteriaFiles.unshift(guidaRapida);
        }

        const combinedPath = join(CONFIG.outputDir, '_temp_segreteria_combined.md');
        await combineMarkdownFiles(segreteriaFiles, combinedPath, 'Manuale Segreteria FTM');

        const success = await convertToPdf(
            combinedPath,
            join(CONFIG.outputDir, 'Manuale_Segreteria_Completo.pdf'),
            'Manuale Segreteria Completo'
        );
        if (success) successCount++; else failCount++;
    }

    // Manuale Completo (tutto insieme)
    console.log('\n--- Manuale Completo FTM ---');
    const allFiles = [];

    // Indice
    if (existsSync(join(CONFIG.manualsDir, 'INDICE.md'))) {
        allFiles.push(join(CONFIG.manualsDir, 'INDICE.md'));
    }

    // Guida rapida
    if (existsSync(join(CONFIG.manualsDir, '00_GUIDA_RAPIDA.md'))) {
        allFiles.push(join(CONFIG.manualsDir, '00_GUIDA_RAPIDA.md'));
    }

    // Coach
    if (existsSync(coachDir)) {
        const coachFiles = (await readdir(coachDir))
            .filter(f => f.endsWith('.md'))
            .sort()
            .map(f => join(coachDir, f));
        allFiles.push(...coachFiles);
    }

    // Segreteria
    if (existsSync(segreteriaDir)) {
        const segreteriaFiles = (await readdir(segreteriaDir))
            .filter(f => f.endsWith('.md'))
            .sort()
            .map(f => join(segreteriaDir, f));
        allFiles.push(...segreteriaFiles);
    }

    // Troubleshooting
    if (existsSync(join(CONFIG.manualsDir, '99_TROUBLESHOOTING.md'))) {
        allFiles.push(join(CONFIG.manualsDir, '99_TROUBLESHOOTING.md'));
    }

    if (allFiles.length > 0) {
        const combinedPath = join(CONFIG.outputDir, '_temp_all_combined.md');
        await combineMarkdownFiles(allFiles, combinedPath, 'Manuale Completo FTM');

        const success = await convertToPdf(
            combinedPath,
            join(CONFIG.outputDir, 'Manuale_FTM_Completo.pdf'),
            'Manuale FTM Completo'
        );
        if (success) successCount++; else failCount++;
    }

    // Pulizia file temporanei
    console.log('\n🧹 Pulizia file temporanei...');
    const tempFiles = ['_temp_coach_combined.md', '_temp_segreteria_combined.md', '_temp_all_combined.md'];
    for (const temp of tempFiles) {
        const tempPath = join(CONFIG.outputDir, temp);
        if (existsSync(tempPath)) {
            const { unlink } = await import('fs/promises');
            await unlink(tempPath);
        }
    }

    // Riepilogo
    console.log('\n' + '='.repeat(60));
    console.log('RIEPILOGO');
    console.log('='.repeat(60));
    console.log(`✅ PDF generati con successo: ${successCount}`);
    console.log(`❌ Errori: ${failCount}`);
    console.log(`📁 Cartella output: ${CONFIG.outputDir}`);
    console.log('='.repeat(60));
}

main().catch(console.error);

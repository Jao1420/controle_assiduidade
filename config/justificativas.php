<?php
/**
 * Justificativas com cores correspondentes à planilha
 * bg    = cor de fundo da célula
 * text  = cor do texto
 * bold  = true quando o texto deve ser negrito
 * group = 'ausencia' | 'trabalho'
 *         ausencia -> ocorrências de falta/afastamento (dias úteis)
 *         trabalho -> trabalho em feriado ou fim de semana
 */
define('JUSTIFICATIVAS', [
    // ── Ausências / afastamentos ──────────────────────────────────
    'COM' => ['label' => 'Compensação',           'bg' => '#008B8B', 'text' => '#ffffff', 'bold' => false, 'group' => 'ausencia'],
    'FER' => ['label' => 'Feriado',               'bg' => '#00CED1', 'text' => '#ffffff', 'bold' => false, 'group' => 'ausencia'],
    'LME' => ['label' => 'Licença Médica',        'bg' => '#87CEEB', 'text' => '#1a1a1a', 'bold' => false, 'group' => 'ausencia'],
    'AT'  => ['label' => 'Atraso',                'bg' => '#808000', 'text' => '#ffffff', 'bold' => false, 'group' => 'ausencia'],
    'SU'  => ['label' => 'Suspensão',             'bg' => '#C8E6C9', 'text' => '#1a1a1a', 'bold' => false, 'group' => 'ausencia'],
    'FE'  => ['label' => 'Férias',                'bg' => '#7B1FA2', 'text' => '#ffffff', 'bold' => false, 'group' => 'ausencia'],
    'FG'  => ['label' => 'Folga',                 'bg' => '#9E9E9E', 'text' => '#1a1a1a', 'bold' => false, 'group' => 'ausencia'],
    'FJ'  => ['label' => 'Falta Justificada',     'bg' => '#FFD700', 'text' => '#000000', 'bold' => true,  'group' => 'ausencia'],
    'SD'  => ['label' => 'Saída',                 'bg' => '#FFDAB9', 'text' => '#1a1a1a', 'bold' => false, 'group' => 'ausencia'],
    'LMA' => ['label' => 'Licença Maternidade',   'bg' => '#5D4037', 'text' => '#ffffff', 'bold' => false, 'group' => 'ausencia'],
    'CH'  => ['label' => 'Crachá',                'bg' => '#00ACC1', 'text' => '#ffffff', 'bold' => false, 'group' => 'ausencia'],
    'F'   => ['label' => 'Falta Não Justificada', 'bg' => '#E53935', 'text' => '#ffffff', 'bold' => true,  'group' => 'ausencia'],
    'P'   => ['label' => 'Presença',              'bg' => '#81C784', 'text' => '#1a1a1a', 'bold' => false, 'group' => 'ausencia'],
    // ── Trabalho em feriado / fim de semana ───────────────────────
    'HE'  => ['label' => 'Hora Extra',            'bg' => '#E65100', 'text' => '#ffffff', 'bold' => true,  'group' => 'trabalho'],
    'BH'  => ['label' => 'Banco de Horas',        'bg' => '#1565C0', 'text' => '#ffffff', 'bold' => false, 'group' => 'trabalho'],
]);

/**
 * Returns the inline-style string for a justificativa cell
 */
function cellStyle(string $code): string
{
    $j = JUSTIFICATIVAS[$code] ?? JUSTIFICATIVAS['P'];
    $fw = $j['bold'] ? 'font-weight:700;' : '';
    return "background:{$j['bg']};color:{$j['text']};{$fw}";
}

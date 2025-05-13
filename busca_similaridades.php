<?php

/**
 * Converte centímetros para polegadas.
 *
 * @param float $cm Valor em centímetros.
 * @return float Valor convertido em polegadas.
 */
function fConverteCMPL($cm)
{
    return $cm / 2.54;
}

/**
 * Converte quilogramas para libras.
 *
 * @param float $kg Valor em quilogramas.
 * @return float Valor convertido em libras.
 */
function fConverteKGLB($kg)
{
    return $kg / 0.453592;
}

/**
 * Converte polegadas para centímetros.
 *
 * @param float $inches Valor em polegadas.
 * @return float Valor convertido em centímetros.
 */
function fConvertePLCM($inches)
{
    return $inches * 2.54;
}

/**
 * Converte libras para quilogramas.
 *
 * @param float $lbs Valor em libras.
 * @return float Valor convertido em quilogramas.
 */
function fConverteLBKG($lbs)
{
    return $lbs * 0.453592;
}

/**
 * Converte onças para gramas.
 *
 * @param float $oz Valor em onças.
 * @return float Valor convertido em gramas.
 */
function fConverteONGM($oz)
{
    return $oz * 28.3495;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Armazena entrada do formulário na sessão
    $_SESSION['entrada'] = [
        'gestation' => floatval($_POST['gestation']),
        'parity'    => floatval($_POST['parity']),
        'age'       => floatval($_POST['age']),
        'height'    => fConverteCMPL(floatval($_POST['height'])),
        'weight'    => fConverteKGLB(floatval($_POST['weight'])),
        'smoke'     => intval($_POST['smoke'])
    ];

    // Armazena pesos atribuídos aos atributos
    $_SESSION['pesos'] = [
        'gestation' => floatval($_POST['peso_gestation']),
        'parity'    => floatval($_POST['peso_parity']),
        'age'       => floatval($_POST['peso_age']),
        'height'    => floatval($_POST['peso_height']),
        'weight'    => floatval($_POST['peso_weight']),
        'smoke'     => floatval($_POST['peso_smoke'])
    ];
}

// Carrega os dados do arquivo CSV
$casos = [];
if (($handle = fopen("base_dados/babies.csv", "r")) !== false) {
    $header = fgetcsv($handle);
    while (($data = fgetcsv($handle)) !== false) {
        $row = array_combine($header, $data);

        // Ignora linhas com dados ausentes
        if (
            $row['gestation'] === '' || $row['parity'] === '' || $row['age'] === '' ||
            $row['height'] === '' || $row['weight'] === '' || $row['smoke'] === '' || $row['bwt'] === ''
        ) {
            continue;
        }

        $casos[] = [
            "id"            => intval($row['case']),
            "gestation"     => floatval($row['gestation']),
            "parity"        => floatval($row['parity']),
            "age"           => floatval($row['age']),
            "height"        => floatval($row['height']),
            "weight"        => floatval($row['weight']),
            "smoke"         => intval($row['smoke']),
            "birth_weight"  => round(fConverteONGM(floatval($row['bwt'])))
        ];
    }
    fclose($handle);
}

// Calcula os valores máximos de cada atributo para normalização
$maximos = [
    'gestation' => max(array_column($casos, 'gestation')),
    'parity'    => max(array_column($casos, 'parity')),
    'age'       => max(array_column($casos, 'age')),
    'height'    => max(array_column($casos, 'height')),
    'weight'    => max(array_column($casos, 'weight'))
];

/**
 * Calcula a similaridade entre um caso e a entrada do usuário.
 *
 * @param array $entrada Dados da entrada do usuário.
 * @param array $caso Dados de um caso da base.
 * @param array $pesos Pesos atribuídos a cada atributo.
 * @param array $maximos Valores máximos por atributo para normalização.
 * @return float Similaridade normalizada entre 0 e 1.
 */
function fCalculaSimilaridade($entrada, $caso, $pesos, $maximos)
{
    $similaridade_total = 0;
    $soma_pesos = array_sum($pesos);
    $campos_numericos = ['gestation', 'parity', 'age', 'height', 'weight'];

    foreach ($campos_numericos as $campo) {
        $diferenca = abs($entrada[$campo] - $caso[$campo]);
        $sim_local = 1 - ($diferenca / $maximos[$campo]);
        $similaridade_total += $sim_local * $pesos[$campo];
    }

    $sim_smoke = ($entrada['smoke'] == $caso['smoke']) ? 1 : 0;
    $similaridade_total += $sim_smoke * $pesos['smoke'];

    return $similaridade_total / $soma_pesos;
}

// Processa entrada do formulário novamente para análise
$entrada = [
    'gestation' => floatval($_POST['gestation']),
    'parity'    => floatval($_POST['parity']),
    'age'       => floatval($_POST['age']),
    'height'    => fConverteCMPL(floatval($_POST['height'])),
    'weight'    => fConverteKGLB(floatval($_POST['weight'])),
    'smoke'     => intval($_POST['smoke'])
];

$pesos = [
    'gestation' => floatval($_POST['peso_gestation']),
    'parity'    => floatval($_POST['peso_parity']),
    'age'       => floatval($_POST['peso_age']),
    'height'    => floatval($_POST['peso_height']),
    'weight'    => floatval($_POST['peso_weight']),
    'smoke'     => floatval($_POST['peso_smoke'])
];

// Calcula a similaridade entre todos os casos e a entrada
$resultados = [];
foreach ($casos as $caso) {
    $sim = fCalculaSimilaridade($entrada, $caso, $pesos, $maximos);
    $caso['similaridade'] = round($sim * 100, 2);
    $resultados[] = $caso;
}

// Ordena os resultados pela similaridade (decrescente)
usort($resultados, fn($a, $b) => $b['similaridade'] <=> $a['similaridade']);

// Paginação dos resultados
$pagina_atual = isset($_POST['pagina']) ? max(1, intval($_POST['pagina'])) : 1;
$por_pagina = 5;
$total_casos = count($resultados);
$total_paginas = ceil($total_casos / $por_pagina);
$inicio = ($pagina_atual - 1) * $por_pagina;
$fatiado = array_slice($resultados, $inicio, $por_pagina);

// Exibe os resultados em uma tabela HTML
echo '<div class="table-responsive">';
echo '<table class="table table-bordered">';
echo "<thead><tr>
        <th class='text-center'>Caso</th>
        <th>Gestação</th>
        <th>Partos</th>
        <th>Idade</th>
        <th>Altura (cm)</th>
        <th>Peso (kg)</th>
        <th>Fumante</th>
        <th>Peso ao nascer (g)</th>
        <th class='text-center'>Similaridade (%)</th>
    </tr></thead><tbody>";

foreach ($fatiado as $caso) {
    $altura_cm = round(fConvertePLCM($caso['height']), 1);
    $peso_kg = round(fConverteLBKG($caso['weight']), 1);
    echo "<tr>
        <td class='text-center'>{$caso['id']}</td>
        <td>{$caso['gestation']}</td>
        <td>{$caso['parity']}</td>
        <td>{$caso['age']}</td>
        <td>{$altura_cm}</td>
        <td>{$peso_kg}</td>
        <td>" . ($caso['smoke'] ? 'Sim' : 'Não') . "</td>
        <td>{$caso['birth_weight']}</td>
        <td class='text-center'>{$caso['similaridade']}%</td>
    </tr>";
}

echo '</tbody></table>';
echo '</div>';

// Navegação de páginas
echo '<nav aria-label="Page navigation">';
echo '<ul class="pagination justify-content-center">';

if ($pagina_atual > 1) {
    echo '<li class="page-item"><a class="page-link" href="#" onclick="fBuscarPagina(' . ($pagina_atual - 1) . ')">Anterior</a></li>';
}

$max_links = 5;
$start = max(1, $pagina_atual - floor($max_links / 2));
$end = min($total_paginas, $start + $max_links - 1);

for ($i = $start; $i <= $end; $i++) {
    $active = $i == $pagina_atual ? 'active' : '';
    echo '<li class="page-item ' . $active . '">';
    echo '<a class="page-link" href="#" onclick="fBuscarPagina(' . $i . ')">' . $i . '</a>';
    echo '</li>';
}

if ($pagina_atual < $total_paginas) {
    echo '<li class="page-item"><a class="page-link" href="#" onclick="fBuscarPagina(' . ($pagina_atual + 1) . ')">Próxima</a></li>';
}

echo '</ul></nav>';

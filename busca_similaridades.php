<?php

function showArray($array)
{
    echo '<pre>';
    var_dump($array);
    echo '</pre>';
}

/**
 * Converte polegadas para centímetros.
 */
function fConvertePolegadasParaCm($polegadas)
{
    if (empty($polegadas)) return 0;
    return $polegadas * 2.54;
}

/**
 * Converte libras para quilogramas.
 */
function fConverteLibrasParaKg($libras)
{
    if (empty($libras)) return $libras;
    return $libras * 0.453592;
}

/**
 * Normalização min-max.
 */
function fNormaliza($valores)
{
    $min = min($valores);
    $max = max($valores);
    return array_map(function ($v) use ($min, $max) {
        return ($max - $min) == 0 ? 0 : ($v - $min) / ($max - $min);
    }, $valores);
}

// 1. Dados do formulário
$entrada = [
    'gestation' => floatval($_POST['gestation']),
    'parity'    => floatval($_POST['parity']),
    'age'       => floatval($_POST['age']),
    'height'    => fConvertePolegadasParaCm(floatval($_POST['height'])),
    'weight'    => fConverteLibrasParaKg(floatval($_POST['weight'])),
    'smoke'     => floatval($_POST['smoke'])
];

// 2. Pesos do formulário (normalizados dividindo por 10)
$pesos = [
    'gestation' => $_POST['peso_gestation'] / 10,
    'parity'    => $_POST['peso_parity'] / 10,
    'age'       => $_POST['peso_age'] / 10,
    'height'    => floatval($_POST['peso_height']) / 10,
    'weight'    => floatval($_POST['peso_weight']) / 10,
    'smoke'     => floatval($_POST['peso_smoke']) / 10
];

// 3. Carrega base de dados
$dados = [];
if (($handle = fopen("base_dados/babies.csv", "r")) !== FALSE) {
    $header = fgetcsv($handle); // pula cabeçalho
    while (($row = fgetcsv($handle)) !== FALSE) {
        $registro = [
            'case'      => intval($row[0]),
            'gestation' => floatval($row[1]),
            'parity'    => floatval($row[2]),
            'age'       => floatval($row[3]),
            'height'    => fConvertePolegadasParaCm(floatval($row[4])),
            'weight'    => fConverteLibrasParaKg(floatval($row[5])),
            'smoke'     => floatval($row[6]),
            'bwt'       => floatval($row[7]) // onças
        ];
        if (in_array(0, $registro)) continue;
        $dados[] = $registro;
    }
    fclose($handle);
}

// 4. Normaliza atributos da base
$atributos = ['case', 'gestation', 'parity', 'age', 'height', 'weight', 'smoke'];
$valores_normalizados = [];

foreach ($atributos as $attr) {
    if ($attr == 'case') continue;
    $coluna = array_column($dados, $attr);
    $valores_normalizados[$attr] = fNormaliza($coluna);
}

foreach ($dados as $i => $item) {
    foreach ($atributos as $attr) {
        if ($attr == 'case') continue;
        $dados[$i]["norm_$attr"] = $valores_normalizados[$attr][$i];
    }
}

// 5. Normaliza a entrada com base nos min/max da base
$entrada_normalizada = [];
foreach ($atributos as $attr) {
    if ($attr == 'case') continue;
    $coluna = array_column($dados, $attr);
    $min = min($coluna);
    $max = max($coluna);
    $entrada_normalizada["norm_$attr"] = ($max - $min) == 0 ? 0 : ($entrada[$attr] - $min) / ($max - $min);
}

// 6. Calcula distância euclidiana ponderada
$resultados = [];
foreach ($dados as $item) {
    $distancia = 0;
    foreach ($atributos as $attr) {
        if ($attr == 'case') continue;
        $d = $entrada_normalizada["norm_$attr"] - $item["norm_$attr"];
        $distancia += $pesos[$attr] * pow($d, 2);
    }
    $item['distancia'] = sqrt($distancia);
    $resultados[] = $item;
}

// 7. Ordena e calcula similaridade
usort($resultados, fn($a, $b) => $a['distancia'] <=> $b['distancia']);
$dist_max = max(array_column($resultados, 'distancia'));

foreach ($resultados as &$r) {
    $r['similaridade'] = 100 - (($r['distancia'] / $dist_max) * 100);
}


// 8. Mostra os top 10 resultados
echo "<table class='table table-bordered'>";
echo "<thead><tr>
<th>ID do Caso</th>
<th>Gestação (dias)</th>
<th>Partos</th>
<th>Idade Mãe</th>
<th>Altura (cm)</th>
<th>Peso (kg)</th>
<th>Fumante</th>
<th>Peso Bebê (g)</th>
<th>Similaridade (%)</th>
</tr></thead><tbody>";

foreach (array_slice($resultados, 0, 10) as $r) {
    $bwt_gramas = $r['bwt'] * 28.3495; // onças para gramas
    echo "<tr>
        <td>{$r['case']}</td>
        <td>{$r['gestation']}</td>
        <td>{$r['parity']}</td>
        <td>{$r['age']}</td>
        <td>" . number_format($r['height'], 1) . "</td>
        <td>" . number_format($r['weight'], 1) . "</td>
        <td>" . ($r['smoke'] ? 'Sim' : 'Não') . "</td>
        <td>" . number_format($bwt_gramas, 0) . "</td>
        <td>" . number_format($r['similaridade'], 1) . "%</td>
    </tr>";
}

echo "</tbody></table>";

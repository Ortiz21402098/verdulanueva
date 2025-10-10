<?php
echo "<h2>Usuario actual de Apache:</h2>";
echo "<pre>";
echo "Usuario PHP: " . get_current_user() . "\n";
echo "</pre>";

echo "<h3>Test de comando print:</h3>";
$tempFile = tempnam(sys_get_temp_dir(), 'test_');
file_put_contents($tempFile, "TEST DE IMPRESION DESDE PHP\n\n\n");

$cmd = 'print /D:"POS-58" "' . $tempFile . '" 2>&1';
exec($cmd, $output, $code);

echo "Comando: <code>$cmd</code><br>";
echo "Código retorno: $code<br>";
echo "Output: <pre>" . implode("\n", $output) . "</pre>";

unlink($tempFile);

if ($code === 0) {
    echo "<p style='color:green; font-size:20px'>✓ Apache tiene permisos para imprimir</p>";
    echo "<p>Si no salió papel, verifica que la impresora esté encendida.</p>";
} else {
    echo "<p style='color:red; font-size:20px'>✗ Apache NO puede imprimir</p>";
    echo "<p>Verifica en services.msc que Apache corra con tu usuario.</p>";
}
?>
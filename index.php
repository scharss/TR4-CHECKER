<?php
require 'vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$openaiApiKey = $_ENV['OPENAI_API_KEY'];
$responseMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $image = $_FILES['image'];
    $uploadDir = __DIR__ . '/uploads/';
    $imagePath = $uploadDir . basename($image['name']);
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedTypes = ['image/png', 'image/jpeg'];
    if (in_array($image['type'], $allowedTypes)) {
        if (move_uploaded_file($image['tmp_name'], $imagePath)) {
            $responseMessage = analyzeImageWithOpenAI($imagePath, $openaiApiKey);
        } else {
            $responseMessage = 'Error al subir la imagen. Verifica los permisos de escritura del servidor.';
        }
    } else {
        $responseMessage = 'Por favor, sube una imagen válida (JPG o PNG).';
    }
}

function analyzeImageWithOpenAI($imagePath, $apiKey) {
    $imageData = base64_encode(file_get_contents($imagePath));
    
    $data = [
        "model" => "gpt-4o",
        "messages" => [
            [
                "role" => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => "Analiza esta imagen de una planta banano y describe detalladamente los síntomas que observas. Evalúa la probabilidad de que la planta esté infectada con Fusarium TR4 u otras enfermedades, proporcionando un porcentaje de confianza para tu diagnóstico de Fusarium TR4. Basándote en los síntomas, indica en qué etapa de la enfermedad podría encontrarse (inicial, intermedia o avanzada) y explica detalladamente por qué llegaste a esa conclusión."
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => "data:image/jpeg;base64,$imageData"
                        ]
                    ]
                ]
            ]
        ],
        "max_tokens" => 2000
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Authorization: Bearer $apiKey"
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return "Error en la conexión con OpenAI: $error";
    }

    $result = json_decode($response, true);

    if (isset($result['choices'][0]['message']['content'])) {
        return trim($result['choices'][0]['message']['content']);
    } else {
        return "No se pudo obtener una respuesta válida de OpenAI. Detalles: " . json_encode($result);
    }
}
?>

<?php
// ... [El resto del código PHP permanece sin cambios]

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TR4 Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .resized-image {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">TR4 AI Checker</h1>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="image" class="form-label">Sube una imagen </label>
                <input class="form-control" type="file" name="image" id="image" accept="image/*" required>
            </div>
            <button type="submit" class="btn btn-primary">Subir Imagen</button>
        </form>

        <?php if (!empty($responseMessage)): ?>
            <div class="mt-5">
                <h3>Resultado del análisis:</h3>
                <?php
                $analysisText = nl2br(htmlspecialchars($responseMessage));
                // Resaltar la fase del Fusarium TR4
                $analysisText = preg_replace('/(fase inicial|fase media|fase terminal)/i', '<strong class="text-danger">$1</strong>', $analysisText);
                echo $analysisText;
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($imagePath)): ?>
            <div class="mt-5">
                <h3>Imagen subida:</h3>
                <img src="<?php echo htmlspecialchars('uploads/' . basename($imagePath)); ?>" alt="Imagen de la planta" class="resized-image img-fluid">
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <br><br><br><br>
</body>
</html>
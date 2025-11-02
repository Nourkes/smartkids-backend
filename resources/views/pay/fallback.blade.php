<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ouverture SmartKids</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }
        p {
            color: #666;
            margin-bottom: 20px;
        }
        .btn {
            display: block;
            width: 100%;
            background: #667eea;
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            text-align: center;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:active {
            transform: scale(0.98);
            background: #5568d3;
        }
        .info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin-top: 20px;
            font-size: 13px;
            color: #666;
        }
        .info strong {
            color: #333;
            display: block;
            margin-bottom: 5px;
        }
        .url {
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            background: white;
            padding: 8px;
            border-radius: 6px;
            margin-top: 5px;
        }
        .status {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            color: #666;
            min-height: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎓 Ouverture de l'app SmartKids...</h1>
        
        <p>Si rien ne s'ouvre, appuyez sur le bouton ci-dessous :</p>
        
        <button class="btn" id="openApp">
            Ouvrir l'app SmartKids
        </button>

        <div class="status" id="status"></div>
        
        <div class="info">
            <strong>📱 L'application ne s'ouvre pas ?</strong>
            <p style="margin: 10px 0;">Copiez ces liens dans l'app :</p>
            <div>
                <strong>Devis (GET):</strong>
                <div class="url">{{ $quote }}</div>
            </div>
            <div style="margin-top: 10px;">
                <strong>Confirmation (POST):</strong>
                <div class="url">{{ $confirm }}</div>
            </div>
        </div>
    </div>

    <script>
        // Récupérer les URLs depuis PHP (encodage sécurisé)
        const deepLink = @json($scheme);
        const intentUrl = @json($intent);
        const statusEl = document.getElementById('status');
        const btn = document.getElementById('openApp');

        console.log('Deep Link:', deepLink);
        console.log('Intent URL:', intentUrl);

        function setStatus(msg) {
            statusEl.innerHTML = msg;
        }

        function tryOpenApp() {
            console.log('Tentative ouverture app...');
            setStatus('⏳ Ouverture de l\'app...');
            
            // Méthode 1: Redirection directe
            window.location.href = deepLink;

            // Méthode 2: Fallback avec Intent pour Android
            setTimeout(function() {
                if (/android/i.test(navigator.userAgent)) {
                    console.log('Tentative avec Intent...');
                    window.location.href = intentUrl;
                }
            }, 1500);

            // Détecter si l'app s'est ouverte
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    setStatus('✅ Ouverture réussie !');
                }
            }, { once: true });

            // Message si l'app ne s'ouvre pas
            setTimeout(function() {
                setStatus('⚠️ Réessayez ou utilisez les liens ci-dessous');
            }, 3000);
        }

        // Tentative automatique au chargement
        window.addEventListener('load', function() {
            setTimeout(tryOpenApp, 500);
        });

        // Sur clic du bouton
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            tryOpenApp();
        });
    </script>
</body>
</html>
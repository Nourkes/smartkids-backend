<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: #8BC34A;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .content {
            background: #f9f9f9;
            padding: 20px;
        }

        .highlight {
            background: #fff;
            border-left: 4px solid #8BC34A;
            padding: 15px;
            margin: 20px 0;
        }

        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #8BC34A;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }

        .footer {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 30px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Réinscription confirmée</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{ $parentName }}</strong>,</p>

            <p>Nous avons le plaisir de vous confirmer la réinscription de votre enfant
                <strong>{{ $childName }}</strong> en classe de <strong>{{ $className }}</strong> pour l'année scolaire
                <strong>{{ $year }}</strong>.</p>

            <div class="highlight">
                <p>✅ <strong>La réinscription a été effectuée avec succès !</strong></p>
                <p>Connectez-vous à l'application SmartKids avec vos identifiants habituels pour continuer à suivre la
                    scolarité de votre enfant.</p>
            </div>

            <p>Cette nouvelle année, vous pourrez toujours :</p>
            <ul>
                <li>📅 Consulter l'emploi du temps</li>
                <li>✅ Suivre les présences</li>
                <li>🍽️ Voir le menu de la semaine</li>
                <li>📄 Accéder aux bulletins</li>
                <li>💬 Communiquer avec l'équipe pédagogique</li>
            </ul>

            <p>Nous vous souhaitons une excellente année scolaire {{ $year }} !</p>

            <a href="#" class="button">Ouvrir l'application</a>
        </div>

        <div class="footer">
            <p>SmartKids - École Maternelle<br>
                Support : support@smartkids.tn</p>
        </div>
    </div>
</body>

</html>
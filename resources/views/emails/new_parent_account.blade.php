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
            background: #A19AD3;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .content {
            background: #f9f9f9;
            padding: 20px;
        }

        .credentials {
            background: #fff;
            border-left: 4px solid #A19AD3;
            padding: 15px;
            margin: 20px 0;
        }

        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #A19AD3;
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
            <h1>Bienvenue à SmartKids</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{ $parentName }}</strong>,</p>

            <p>Félicitations ! Votre enfant <strong>{{ $childName }}</strong> a été inscrit avec succès en classe de
                <strong>{{ $className }}</strong> pour l'année scolaire <strong>{{ $year }}</strong>.</p>

            <div class="credentials">
                <h3>📧 Vos identifiants de connexion :</h3>
                <p><strong>Email :</strong> {{ $email }}</p>
                <p><strong>Mot de passe temporaire :</strong> <code
                        style="background: #f0f0f0; padding: 5px;">{{ $password }}</code></p>
            </div>

            <p><strong>⚠️ Important :</strong> À votre première connexion, vous devrez changer votre mot de passe pour
                des raisons de sécurité.</p>

            <p>Téléchargez l'application SmartKids pour suivre la scolarité de votre enfant :</p>
            <ul>
                <li>📅 Consulter l'emploi du temps</li>
                <li>✅ Voir les présences</li>
                <li>🍽️ Menu de la semaine</li>
                <li>📄 Bulletins et notes</li>
                <li>💬 Messagerie avec l'école</li>
            </ul>

            <a href="#" class="button">Télécharger l'application</a>
        </div>

        <div class="footer">
            <p>SmartKids - École Maternelle<br>
                Support : support@smartkids.tn</p>
        </div>
    </div>
</body>

</html>
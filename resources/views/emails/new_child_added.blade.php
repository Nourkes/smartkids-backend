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
            background: #A1D6CB;
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
            border-left: 4px solid #A1D6CB;
            padding: 15px;
            margin: 20px 0;
        }

        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #A1D6CB;
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
            <h1>👶 Nouvel enfant ajouté</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{ $parentName }}</strong>,</p>

            <p>Nous avons le plaisir de vous informer que votre enfant <strong>{{ $childName }}</strong> a été inscrit
                avec succès en classe de <strong>{{ $className }}</strong> pour l'année scolaire
                <strong>{{ $year }}</strong>.</p>

            <div class="highlight">
                <p>📱 <strong>Votre enfant a été ajouté à votre compte existant.</strong></p>
                <p>Connectez-vous à l'application SmartKids avec vos identifiants habituels pour accéder à son dossier.
                </p>
            </div>

            <p>Vous pouvez maintenant :</p>
            <ul>
                <li>📅 Consulter son emploi du temps</li>
                <li>✅ Suivre ses présences</li>
                <li>📄 Voir ses bulletins et notes</li>
                <li>💬 Communiquer avec ses enseignants</li>
            </ul>

            <a href="#" class="button">Ouvrir l'application</a>
        </div>

        <div class="footer">
            <p>SmartKids - École Maternelle<br>
                Support : support@smartkids.tn</p>
        </div>
    </div>
</body>

</html>
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
            background: #FFA726;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .content {
            background: #f9f9f9;
            padding: 20px;
        }

        .message {
            background: #fff;
            border-left: 4px solid #FFA726;
            padding: 15px;
            margin: 20px 0;
        }

        .position {
            background: #FFF3E0;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
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
            <h1>⏳ Liste d'attente</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{ $parentName }}</strong>,</p>

            <p>Nous avons bien reçu votre demande d'inscription pour <strong>{{ $childName }}</strong> en niveau
                <strong>{{ $niveau }}</strong>.</p>

            <div class="message">
                <p><strong>Votre demande a été placée sur liste d'attente.</strong></p>
                <p>Nous n'avons actuellement pas de places disponibles dans ce niveau, mais nous gardons votre demande
                    en file d'attente.</p>
            </div>

            <div class="position">
                <h2 style="margin: 0; color: #F57C00;">Position actuelle : #{{ $position }}</h2>
            </div>

            <p><strong>Que se passe-t-il maintenant ?</strong></p>
            <ul>
                <li>Votre demande reste active dans notre système</li>
                <li>Nous vous contacterons dès qu'une place se libère</li>
                <li>Vous recevrez un email avec un lien de paiement si une place devient disponible</li>
                <li>Vous aurez 3 jours pour confirmer votre inscription</li>
            </ul>

            <p>Merci de votre patience et de votre confiance en SmartKids.</p>
        </div>

        <div class="footer">
            <p>SmartKids - École Maternelle<br>
                Support : support@smartkids.tn<br>
                Tél : +216 12 345 678</p>
        </div>
    </div>
</body>

</html>
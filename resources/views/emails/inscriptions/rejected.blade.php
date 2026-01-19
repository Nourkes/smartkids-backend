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
            background: #E57373;
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
            border-left: 4px solid #E57373;
            padding: 15px;
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
            <h1>❌ Demande d'inscription</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{ $parentName }}</strong>,</p>

            <p>Nous avons examiné votre demande d'inscription pour <strong>{{ $childName }}</strong>.</p>

            <div class="message">
                <p><strong>Malheureusement, nous ne pouvons pas donner suite à votre demande.</strong></p>

                @if($remarques)
                    <p><strong>Raison :</strong></p>
                    <p>{{ $remarques }}</p>
                @endif
            </div>

            <p>Si vous souhaitez plus d'informations ou discuter de cette décision, n'hésitez pas à nous contacter.</p>

            <p>Vous pouvez soumettre une nouvelle demande d'inscription si votre situation change.</p>
        </div>

        <div class="footer">
            <p>SmartKids - École Maternelle<br>
                Support : support@smartkids.tn<br>
                Tél : +216 12 345 678</p>
        </div>
    </div>
</body>

</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LLM-IoT Environmental Recommendation System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        /* Page layout */
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }

        .llm-container {
            background: rgba(255, 255, 255, 0.97);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.18);
            width: 100%;
            max-width: 700px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .llm-page-title {
            font-size: 22px;
            font-weight: 700;
            text-align: center;
            line-height: 1.4;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .llm-field-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .llm-field-label {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
        }

        .llm-field-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 15px;
            color: #333;
            background: #f8f9fa;
            font-family: inherit;
        }

        .llm-field-textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 15px;
            color: #333;
            background: #f8f9fa;
            resize: none;
            line-height: 1.6;
            font-family: inherit;
        }

        .llm-status {
            font-size: 13px;
            font-weight: 500;
            min-height: 20px;
            text-align: center;
            color: #888;
        }

        .llm-status.error {
            color: #dc3545;
        }

        .llm-button-row {
            display: flex;
            gap: 16px;
            justify-content: center;
        }

        @media (max-width: 500px) {
            .llm-container {
                padding: 32px 20px;
            }
            .llm-page-title {
                font-size: 18px;
            }
            .llm-button-row {
                flex-direction: column;
                align-items: stretch;
            }
            .llm-button-row .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="llm-container">

        <!-- Title -->
        <h1 class="llm-page-title">
            LLM-IoT Environmental Recommendation System
        </h1>

        <!-- Timestamp Field -->
        <div class="llm-field-group">
            <span class="llm-field-label">Timestamp</span>
            <input
                type="text"
                id="timestamp-field"
                class="llm-field-input"
                placeholder="— not loaded yet —"
                readonly
            >
        </div>

        <!-- Advice Field -->
        <div class="llm-field-group">
            <span class="llm-field-label">Recommendation</span>
            <textarea
                id="advice-field"
                class="llm-field-textarea"
                rows="10"
                maxlength="500"
                placeholder="— click Generate to load the latest recommendation —"
                readonly
            ></textarea>
        </div>

        <!-- Status Message -->
        <div id="llm-status" class="llm-status"></div>

        <!-- Buttons -->
        <div class="llm-button-row">
            <button class="btn btn-primary" id="generate-btn" onclick="generateAdvice()">
                Generate
            </button>
            <button class="btn btn-secondary" onclick="window.location.href='https://scs.org.sa'">
                Return
            </button>
        </div>

    </div>

    <script>
        async function generateAdvice() {
            const btn       = document.getElementById('generate-btn');
            const tsField   = document.getElementById('timestamp-field');
            const advField  = document.getElementById('advice-field');
            const statusDiv = document.getElementById('llm-status');

            // Loading state
            btn.disabled          = true;
            btn.textContent       = 'Loading...';
            statusDiv.className   = 'llm-status';
            statusDiv.textContent = 'Fetching latest recommendation...';

            try {
                const response = await fetch('api/get_advice.php');

                if (!response.ok) {
                    throw new Error('Server error: ' + response.status);
                }

                const data = await response.json();

                if (data.ok) {
                    tsField.value         = data.timestamp;
                    advField.value        = data.advise;
                    statusDiv.textContent = 'Recommendation loaded successfully.';
                } else {
                    tsField.value         = '';
                    advField.value        = '';
                    statusDiv.className   = 'llm-status error';
                    statusDiv.textContent = data.err || 'No data available.';
                }

            } catch (error) {
                statusDiv.className   = 'llm-status error';
                statusDiv.textContent = 'Failed to connect to the server. Please try again.';
            } finally {
                btn.disabled    = false;
                btn.textContent = 'Generate';
            }
        }
    </script>
</body>
</html>

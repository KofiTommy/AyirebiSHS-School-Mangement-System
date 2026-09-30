# SmartMark Scanner service

The Python service analyzes standardised objective answer sheets. It does not retain images or results.

```powershell
py -3.11 -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
$env:SMARTMARK_API_TOKEN = 'use-a-long-random-secret'
uvicorn main:app --host 127.0.0.1 --port 8765
```

Copy `../smartmark-service-config.example.php` to `../smartmark-service-config.local.php` and set the same token. The initial detector expects the standard printable layout: 18% header, 10% side margins, and evenly-spaced question rows. Teachers must approve flagged answers before saving results.

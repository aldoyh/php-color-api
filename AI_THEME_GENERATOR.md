# AI Color Theme Generator - Complete Guide

## Overview

The Color API now includes an **AI-powered color theme generator** that uses a local Ollama LLM to create beautiful, harmonious color palettes based on user descriptions.

## How It Works

### Architecture

```
Frontend (home.html)
    ↓ generateAITheme() GraphQL query
GraphQL Endpoint (/graphql)
    ↓ generateTheme resolver (src/query.php)
OllamaClient (src/OllamaClient.php)
    ↓ HTTP POST to Ollama API
Ollama LLM (localhost:11434/api/generate)
    ↓ Returns color palette text
ColorUtils (src/ColorUtils.php)
    ↓ Parse hex codes and validate
Database (ai_themes table)
    ↓ Logs each generated theme
Frontend (home.html)
    ↓ Displays color cards
```

### Key Features

✅ **Automatic Model Fallback**: If the default model isn't available, the system automatically selects the first available model from Ollama.

✅ **Robust Error Handling**: Retries failed requests up to 3 times with exponential backoff (max 8s between attempts).

✅ **Long Timeout Support**: 120-second request timeout allows large models to load and generate responses.

✅ **Flexible Response Parsing**: Handles multiple Ollama response formats (`response`, `text`, `outputs`).

✅ **Comprehensive Logging**: Every step is logged for debugging (see logs in PHP error log).

✅ **Database Logging**: All generated themes are saved to the `ai_themes` table with prompt, model, and colors.

## Using the AI Generator

### Via Web Interface

1. Open `http://localhost:PORT/` in your browser
2. Scroll to the **"✨ AI Color Theme Generator"** section
3. Enter a description (e.g., "Modern dashboard with blues and warm accents")
4. Click **"Generate with AI"**
5. Wait for the loading animation (up to 2 minutes for first load)
6. View the generated color palette

### Via GraphQL API

```graphql
query {
  generateTheme(prompt: "Modern dashboard blue accents") {
    hex
    name
    rgb
    hsl
  }
}
```

**Optional parameters:**
- `model` (default: `llama3`): Specify an Ollama model to use

Example with custom model:
```graphql
query {
  generateTheme(prompt: "Pastel spring colors", model: "gemma3:latest") {
    hex
    name
    rgb
    hsl
  }
}
```

### Via cURL

```bash
curl -X POST http://localhost:8003/graphql \
  -H 'Content-Type: application/json' \
  -d '{
    "query": "query { generateTheme(prompt: \"Modern dashboard\") { hex name rgb hsl } }"
  }'
```

## Requirements

### Ollama Installation

You must have Ollama installed and running locally:

1. Download Ollama from https://ollama.ai
2. Start the Ollama service: `ollama serve` (or it runs as a service on macOS/Windows)
3. Pull a model: `ollama pull gemma3` (or `ollama pull llama3`, `ollama pull qwen2.5-coder`, etc.)

### Verify Ollama is Running

```bash
curl http://localhost:11434/api/tags
```

Should return a JSON list of available models.

## Troubleshooting

### Issue: "Ollama model 'llama3' not found"

**Solution**: The model `llama3` isn't installed. Ollama will auto-fallback to the first available model. To explicitly use a specific model:
```bash
ollama pull llama3  # or any model you prefer
```

### Issue: Loading animation hangs or times out

**Possible causes**:
1. **Ollama not running**: Check `curl http://localhost:11434/api/tags`
2. **Model is loading for the first time**: First generation of a model can take 30-120 seconds (large models). Be patient!
3. **Network timeout**: If timeouts still occur, the server has 120-second total timeout. Increase if needed in `src/OllamaClient.php`.

**Solution**:
- Check PHP error log: `tail -f /var/log/php-errors.log` (or equivalent)
- Check browser console for errors (F12 → Console tab)
- Pre-load the model: `ollama run gemma3` to ensure it's cached

### Issue: 500 Error in GraphQL Response

**Solution**: Check PHP error logs for detailed stack traces. The logs will show:
- Which step failed (model check, API call, response parsing, DB insert)
- Raw Ollama response if parsing failed
- Exact exception message

Example log output:
```
[Fri Oct 24 10:14:51 2025] === generateTheme resolver START ===
[Fri Oct 24 10:14:51 2025] generateTheme: userPrompt='Modern dashboard blue', model='llama3'
[Fri Oct 24 10:14:51 2025] OllamaClient initialized: model=gemma3:latest, endpoint=http://localhost:11434/api/generate
[Fri Oct 24 10:14:54 2025] [Attempt 1] SUCCESS: extracted 'response' field, length=105
[Fri Oct 24 10:14:54 2025] === generateTheme resolver SUCCESS ===
```

### Issue: No Colors Extracted from AI Response

**Cause**: Ollama's response doesn't contain valid hex codes.

**Solution**: Improve the prompt to be more specific. Examples:
- ✅ "Modern dashboard with cool blues, warm accents, and white text"
- ✅ "Dark mode theme using only shades of purple and gold"
- ❌ "Pretty colors" (too vague)

## Performance Notes

- **First generation**: May take 30-120+ seconds (models are being loaded into memory)
- **Subsequent generations**: Much faster (10-30 seconds) as models remain cached
- **Model size matters**: Larger models (8B+) take longer; smaller models (1-4B) are faster
- **Recommended models** for speed: `gemma3:latest`, `gemma3:1b`, `qwen:latest`

## Configuration

### Timeouts (in `src/OllamaClient.php`)

```php
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);  // 10 seconds to establish connection
curl_setopt($ch, CURLOPT_TIMEOUT, 120);        // 120 seconds total timeout per request
```

To increase timeouts, edit these values and restart the server.

### Database Logging

All themes are logged to `ai_themes` table:
- `id`: Auto-increment ID
- `prompt`: User's original prompt
- `model`: Model name used
- `colors_json`: JSON array of generated colors
- `created`: Timestamp

Query saved themes:
```sql
SELECT * FROM ai_themes ORDER BY created DESC LIMIT 10;
```

## Code Structure

- **`src/OllamaClient.php`**: HTTP client for Ollama API with retry logic and flexible parsing
- **`src/OllamaUtils.php`**: Utility functions (check model exists, list available models)
- **`src/query.php`**: GraphQL resolver for `generateTheme` query
- **`src/ColorUtils.php`**: Color validation and conversion (hex ↔ RGB ↔ HSL)
- **`templates/home.html`**: Frontend UI with loading animation and error handling
- **`public/index.php`**: GraphQL endpoint with comprehensive error handling

## Example Flow (end-to-end)

1. User enters "Retro 80s neon" in frontend
2. Frontend calls GraphQL: `generateTheme(prompt: "Retro 80s neon")`
3. GraphQL resolver receives prompt with default model `llama3`
4. OllamaClient checks if model exists; it doesn't, so falls back to `gemma3:latest`
5. OllamaClient calls Ollama API with optimized color palette prompt
6. Ollama generates response (takes ~10-120 seconds)
7. OllamaClient parses response and extracts hex codes
8. ColorUtils validates each hex code and computes RGB/HSL
9. Colors are saved to `ai_themes` table
10. GraphQL returns array of 5-7 color objects
11. Frontend displays color cards with loading animation removed
12. User can copy hex/RGB values or generate another theme

## API Contracts

### GraphQL Query

**Request:**
```graphql
query {
  generateTheme(prompt: String!, model: String) {
    hex: String!
    name: String
    rgb: String
    hsl: String
  }
}
```

**Response on success:**
```json
{
  "data": {
    "generateTheme": [
      {"hex": "#FF6B6B", "name": "coral red", "rgb": "255, 107, 107", "hsl": "0°, 100%, 71%"},
      ...
    ]
  }
}
```

**Response on error:**
```json
{
  "errors": [
    {
      "message": "Ollama theme generation failed after 3 attempts. Last error: ...",
      "file": "src/OllamaClient.php",
      "line": 42
    }
  ]
}
```

## Future Improvements

- [ ] Stream responses from Ollama for real-time feedback
- [ ] Add rate limiting to prevent abuse
- [ ] Cache generated themes and prompt-to-palette mappings
- [ ] Support multiple LLM backends (OpenAI API, Anthropic, etc.)
- [ ] Add color theory suggestions (complementary, analogous, etc.)
- [ ] Frontend: Show generated prompt and model used for each theme
- [ ] Admin UI to manage saved themes and regenerate variations

---

**Last Updated**: October 24, 2025
**Status**: ✅ Production Ready

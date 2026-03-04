# OttoAiMapper – PlentyONE Plugin

**AI-powered automatic field mapping between OTTO Market and PlentyONE catalog data fields.**

---

## Features

- 🤖 **AI Mapping** – Uses OpenAI GPT-4o to semantically match OTTO Market target fields to PlentyONE source fields
- ⚡ **Fully automated** – Run mapping with one click or configure auto-apply on catalog export
- 🎯 **Confidence scoring** – Each mapping proposal includes a 0–100% confidence score
- ✅ **Manual review UI** – Review and override individual field mappings before applying
- 📋 **History** – Tracks the last 20 mapping runs per catalog
- 🔄 **Daily cron** – Optional scheduled auto-remapping for the configured catalog

---

## Plugin Structure

```
OttoAiMapper/
├── plugin.json                         ← Plugin manifest
├── config.json                         ← Plugin configuration (API key, model, etc.)
├── ui.json                             ← Backend UI entry point
├── ui/
│   └── index.html                      ← Plugin backend UI (plain JS, no framework)
├── resources/
│   └── lang/
│       ├── de.json                     ← German translations
│       └── en.json                     ← English translations
└── src/
    ├── Contracts/
    │   └── AiMappingServiceContract.php ← Interface for AI service (swappable)
    ├── Controllers/
    │   ├── AiMappingController.php      ← POST /run-mapping, GET /mapping-result, etc.
    │   ├── CatalogFieldController.php   ← GET /otto-fields, /plenty-fields
    │   └── ConfigController.php        ← GET /config
    ├── Cron/
    │   └── AutoMappingCron.php         ← Daily scheduled mapping
    ├── Providers/
    │   ├── OttoAiMapperServiceProvider.php      ← Main service provider
    │   └── OttoAiMapperRouteServiceProvider.php ← REST routes
    └── Services/
        ├── MappingOrchestratorService.php ← Orchestrates full mapping flow
        ├── MappingStorageService.php      ← Persists results via PlentyONE storage
        └── OpenAiMappingService.php       ← OpenAI API integration
```

---

## REST API Endpoints

| Method   | Path                                        | Description                          |
|----------|---------------------------------------------|--------------------------------------|
| `GET`    | `/rest/otto-ai-mapper/config`               | Get current configuration            |
| `GET`    | `/rest/otto-ai-mapper/otto-fields`          | List OTTO Market target fields       |
| `GET`    | `/rest/otto-ai-mapper/plenty-fields`        | List PlentyONE source fields         |
| `POST`   | `/rest/otto-ai-mapper/run-mapping`          | Run AI mapping for a catalog         |
| `GET`    | `/rest/otto-ai-mapper/mapping-result/{id}`  | Get last mapping result              |
| `POST`   | `/rest/otto-ai-mapper/apply-mapping`        | Apply proposals to catalog           |
| `DELETE` | `/rest/otto-ai-mapper/mapping-result/{id}`  | Delete stored result                 |
| `GET`    | `/rest/otto-ai-mapper/history`              | Get mapping run history              |

---

## Setup

1. Install the plugin in your PlentyONE plugin set
2. Open **Plugins → Plugin overview → OttoAiMapper → Configuration**
3. Enter your **OpenAI API key**
4. Select the **AI model** (GPT-4o recommended)
5. Set the **confidence threshold** (75% recommended)
6. Enter the **OTTO Catalog ID** from your PlentyONE catalog
7. Deploy the plugin set
8. Open **OttoAiMapper** in the PlentyONE backend sidebar
9. Click **⚡ Run AI Mapping**

---

## Extending

To use a different LLM, implement `AiMappingServiceContract` and rebind it in the ServiceProvider:

```php
$this->getApplication()->singleton(
    AiMappingServiceContract::class,
    YourCustomLlmService::class
);
```

---

## Requirements

- PlentyONE system (stable7+)
- PHP ≥ 7.4
- OpenAI API key (GPT-4o access recommended)
- `guzzlehttp/guzzle` ^7.0 (via `dependencies` in `plugin.json`)

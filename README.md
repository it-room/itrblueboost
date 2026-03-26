# ITR Blue Boost - PrestaShop Module

Integration module with ITROOM API for intelligent data synchronization with PrestaShop.

## Overview

ITR Blue Boost is a PrestaShop module that seamlessly integrates with the ITROOM API to provide:
- AI-powered product and category description generation
- Intelligent product image generation
- API-based FAQ fetching with file-based caching (v2.0.0)
- Complete admin interface for managing generated content
- Real-time credit tracking

## Features

- **Product Description Generation**: Generate AI-powered product descriptions and short descriptions
- **Category Description Generation**: Generate AI-powered category descriptions, additional descriptions, and SEO fields (meta title, meta description, meta keywords) (new in v1.8.17, SEO in v1.8.18)
- **Product FAQ Display**: Fetch and display frequently asked questions for products from the external API with file-based caching (read-only, v2.0.0)
- **Category FAQ Display**: Fetch and display FAQs at the category level from the external API with file-based caching (read-only, v2.0.0)
- **CMS Page FAQ Display**: Fetch and display FAQs on CMS content pages from the external API with file-based caching (read-only, v2.0.3)
- **AI Image Generation**: Generate product images using ITROOM API with async processing to prevent HTTP 504 timeouts
- **Bulk Image Generation with Cover Images**: Automatically sends product cover images to the API for improved generation results
- **Async Generation Jobs**: Image generation runs in background via Symfony command with progress tracking
- **Animated Progress Bar**: Real-time progress display with step indicators (Start → API Call → Generation → Save → Done) and percentage completion
- **Job Status Polling**: Frontend automatically polls for job status updates every 2 seconds with fallback to manual refresh
- **Fallback Processing**: Automatic fallback to inline processing using `fastcgi_finish_request()` if command execution is unavailable
- **Inline Content Generation**: Generate descriptions directly from product/category edit form with inline buttons
- **Bulk Operations**: Generate content and images in bulk for multiple products/categories; perform Accept All, Reject All, and Delete All operations on generated content
- **Flexible View Modes**: Toggle between grid view (cards) and list view (table) with automatic preference persistence
- **Checkbox Selection System**: Select multiple items across grid/list views with synchronized checkboxes and visual feedback
- **Floating Bulk Toolbar**: Context-aware floating action toolbar appears when items are selected for quick bulk operations
- **Theme Compatibility Settings**: Configure Bootstrap version compatibility (Bootstrap 4, Bootstrap 4 Alpha, Bootstrap 5) for proper theme integration
- **API Mode Selection**: Switch between Production and Test API environments without code changes
- **Admin Dashboard**: Complete management interface for all generated content
- **Credit System**: Track remaining API credits directly from admin header
- **Multi-shop Support**: Compatible with multi-shop PrestaShop installations
- **Language Support**: Support for all PrestaShop languages
- **Modern Admin UI**: Symfony-based modern admin controllers
- **Front-office Display**: Automatically displays generated content (descriptions and FAQs) on product and category pages
- **Content Listing Badges**: Visual badges in product and category admin listings showing counts of generated content and images at a glance
- **Complete API Logging**: All API calls (image generation, content generation, account info) are logged with full request/response details, context, and error messages
- **itrmicrodata Integration**: Hooks into itrmicrodata module to provide AI-generated product descriptions for JSON-LD structured data (Product schema on product pages and ItemList schema on listings), with batch preloading to avoid N+1 queries (new in v1.8.19)
- **Automatic Webservice Key**: Creates a PrestaShop webservice key at install/upgrade with full permissions on all resources, syncs with ITROOM API automatically (new in v1.8.20)
- **Module Auto-Updates**: Automatically checks for new GitHub releases and notifies admins when updates are available; one-click update installation with CSRF protection (new in v1.8.21)
- **Cache Management**: Toggle FAQ caching on/off from Settings page; module caches are automatically cleared when PrestaShop cache is cleared (new in v2.0.2)

## Requirements

- PrestaShop: 1.7.8.2 to 9.x
- PHP: 7.1 or higher
- ITROOM API Key (required for functionality)

## Installation

1. Download and extract the module to `modules/itrblueboost/`
2. In PrestaShop admin, navigate to: **Modules** → **Module Manager**
3. Search for "ITR Blue Boost" and click **Install**
4. The module will automatically create database tables and admin menu items

After installation, the module creates:
- Database tables for storing generated content and images
- Admin menu in the "Configurer" (Configure) section with sub-menus
- Default configuration values
- Cache directory for FAQ caching (`var/cache/faq/`)

## Configuration

Once installed, configure the module:

1. Go to **Configurer** → **ITR Blue Boost** → **Settings** in the admin menu
2. Enter your ITROOM API Key
3. Enable desired services:
   - Product image generation
   - Product description generation (optional)
   - Category description generation (optional)

The remaining API credits are displayed as a badge in the admin header (see **Performance Optimization** below).

### Theme Compatibility

To ensure compatibility with your site theme:

1. From the Configuration page, click the **Paramètre** button in the toolbar
2. Select the Bootstrap version used by your theme from the dropdown:
   - **Bootstrap 4**: Standard Bootstrap 4 framework
   - **Bootstrap 4 Alpha**: Bootstrap 4 alpha version
   - **Bootstrap 5**: Latest Bootstrap 5 framework
3. Default value is **Bootstrap 5**

The selected version is stored in the configuration and affects frontend presentation of module elements.

### API Mode Configuration

Configure which ITROOM API environment the module uses:

1. From the Configuration page, click the **Paramètre** button in the toolbar
2. Select your desired API mode from the dropdown:
   - **Production** (default): Uses `apitr-sf.itroom.fr` - for live operations
   - **Test**: Uses `blueboost.itroom.fr` - for testing and development
3. Default value is **Production**

The selected API mode is stored in the configuration. Both ApiService and ApiLogger dynamically resolve the API base URL based on this setting, ensuring all API calls connect to the correct endpoint.

#### How Bootstrap Version Affects FAQ Display

The Bootstrap version setting directly controls how FAQ accordions are rendered on the front-office:

- **FAQ Template Adaptation**: Both product FAQs (product_faq.tpl) and category FAQs (category_faq.tpl) contain conditional logic that renders different HTML markup depending on the selected Bootstrap version
- **CSS Classes**: Bootstrap 5 uses `accordion-*` classes while Bootstrap 4 uses `card-*` classes for styling
- **JavaScript Attributes**: Bootstrap 5 uses `data-bs-*` attributes (data-bs-toggle, data-bs-target, data-bs-parent) while Bootstrap 4 uses `data-*` attributes (data-toggle, data-target, data-parent) for collapse functionality
- **Automatic Application**: Once you select your theme's Bootstrap version, all FAQ accordions will use the appropriate markup automatically—no additional template editing required

This ensures that FAQ accordions work correctly with your theme's Bootstrap implementation and maintain proper styling and interactivity.

### Configuration Keys

The module uses the following configuration keys (stored in `ps_configuration`):

- `ITRBLUEBOOST_API_KEY`: Your ITROOM API key (required)
- `ITRBLUEBOOST_ENABLE_PRODUCT_IMAGE`: Enable/disable product image generation
- `ITRBLUEBOOST_ENABLE_PRODUCT_CONTENT`: Enable/disable product description generation
- `ITRBLUEBOOST_ENABLE_CATEGORY_CONTENT`: Enable/disable category description generation (new in v1.8.17)
- `ITRBLUEBOOST_BOOTSTRAP_VERSION`: Selected Bootstrap version (bootstrap4, bootstrap4alpha, or bootstrap5; default: bootstrap5)
- `ITRBLUEBOOST_API_MODE`: API environment mode (prod or test; default: prod)
- `ITRBLUEBOOST_CREDITS_REMAINING`: Stores the last known remaining API credits (automatically updated)
- `ITRBLUEBOOST_UPDATE_CACHE`: Cached GitHub release information for update checks (expires after 1 hour)
- `ITRBLUEBOOST_FAQ_CACHE_TTL`: FAQ cache time-to-live in seconds (default: 3600 = 1 hour, new in v2.0.0)
- `ITRBLUEBOOST_FAQ_CACHE_ENABLED`: Enable/disable FAQ file-based caching (default: enabled, new in v2.0.2)

### Module Auto-Updates

The module automatically checks for new releases on GitHub and notifies administrators when updates are available. This feature is integrated into the admin dashboard toolbar.

#### How Auto-Updates Work

**Update Check Process:**
1. The module periodically checks the GitHub repository (it-room/itrblueboost) for new releases
2. Update information is cached for 1 hour to avoid exceeding GitHub API rate limits
3. If an update is available, a warning button appears in the admin toolbar
4. If no update is available, an "Up to date" indicator is displayed with the last check timestamp

**Update Installation:**
1. Click the update warning button to open the update details modal
2. The modal displays:
   - Current module version
   - Latest available version on GitHub
   - Release notes (from GitHub release description)
   - A direct link to the full release on GitHub
3. Click "Update" to proceed with installation
4. The module will:
   - Download the release ZIP from GitHub
   - Extract the archive to a temporary directory
   - Replace existing module files with the new version
   - Run PrestaShop's upgrade mechanism to execute any database migrations
   - Clear the update cache to force a fresh check

#### Technical Details

**Security:**
- All update actions are protected by CSRF tokens
- The update action requires admin update permissions
- GitHub API calls use secure HTTPS with proper timeouts

**Error Handling:**
- If GitHub is unreachable, the module uses previously cached data if available
- If the cache is also unavailable, a warning message is displayed
- Missing PHP ZipArchive extension, non-writable module directory, or connection errors are reported with clear error messages
- Failed updates do not affect the current module installation

**Performance:**
- Update checks use a 1-hour cache to minimize API calls to GitHub
- Cache automatically expires after 1 hour, triggering a fresh check on the next access
- Release information from GitHub is cached locally in the PrestaShop configuration

**Compatibility:**
- Requires PHP ZipArchive extension (standard in most PHP installations)
- Module directory must be writable by the web server
- Works with PrestaShop's built-in upgrade mechanism

## Admin Menu Structure

The module creates a dropdown menu in the **Configurer** section with the following sub-menus:

- **Settings**: Configure API key and enable/disable services
- **Compatibility**: Select the Bootstrap version used on your site theme
- **All Product Contents**: Centralized view for managing all AI-generated product descriptions and short descriptions
- **All Category Contents**: Centralized view for managing all AI-generated category descriptions (new in v1.8.17)
- **All generated images**: View and manage all AI-generated product images

Additional contextual tabs are automatically displayed:
- AI Images tab on product edit page (when enabled)
- Content inline generation buttons next to description and short description fields on product/category edit pages (when enabled)
- Category Content inline generation button on category edit page (when enabled)

## Usage

### Generating Product Descriptions

Product descriptions and short descriptions can be generated directly from the product edit form or through the centralized admin interface.

**From Product Edit Form (Inline Generation):**
1. Navigate to a product edit page
2. Locate the "Description" or "Description courte" (Short Description) fields
3. Click the "Generate" button next to the field to generate content using AI
4. The generated content appears as a pending item

**From All Product Contents Menu:**
1. Go to **Configurer** → **ITR Blue Boost** → **All Product Contents**
2. View all generated product descriptions in a paginated list
3. Filter by status: pending, accepted, or rejected
4. For pending contents:
   - **Accept**: Click the accept button to apply the generated content to the product
   - **Reject**: Click the reject button and optionally provide a rejection reason
5. For accepted contents:
   - Toggle the active/inactive status to enable or disable the content
   - Delete contents you no longer need

**Content Workflow:**
- Generated content starts in **pending** status
- Accept pending content to apply it to the product's description or short description
- Rejected content is deleted and its rejection reason is sent to the API
- Accepted content can be toggled active/inactive without losing the data

**Supported Content Types:**
- **Description**: Full product description (long form)
- **Description courte**: Short description (for list views and summaries)

### Product FAQs (Read-Only from API)

Starting with v2.0.0, product FAQs are now fetched read-only from the external API and cached locally for optimal performance. Admin-side FAQ generation and management have been removed.

**How Product FAQs Work:**

1. **Front-Office Display**: Product FAQs are automatically displayed on product pages via the `displayProductExtraContent` hook
2. **API Fetching**: When a product page is viewed, FAQs are fetched from the API endpoint `/api/faq/list?type=product&id_product={id}&lang={lang}`
3. **File-Based Caching**: Fetched FAQs are cached in the file system (`var/cache/faq/`) for subsequent accesses
4. **Cache TTL**: Cache entries expire after the configured TTL (default: 3600 seconds = 1 hour)
5. **Language Support**: FAQs are fetched in the current front-office language automatically

**Configuration:**

- **FAQ_CACHE_TTL**: Customize the cache expiration time in seconds (default: 3600)
  - Set to 0 for no caching (FAQs always fetched from API)
  - Set higher values to reduce API calls and improve performance
  - Cache is cleared automatically when the module is uninstalled

### Generating Product Images

**Individual Product:**
1. Navigate to a product edit page
2. Look for the "AI Product Images" tab
3. Use the interface to generate new images with custom prompts

**Bulk Operations:**
1. Go to product list view
2. Select multiple products
3. Click "Generate Images (AI)" from bulk actions
4. Choose an image prompt from the modal
5. Watch the real-time progress bar as images are generated for each product sequentially
6. Once complete, click on each product link in the results to view the generated images in the product's image management page

**Cover Image Support:**
When generating images in bulk, the module automatically sends each product's cover image URL to the AI API for better generation results. This brings parity with single-product image generation where the base image can be selected manually. The cover image (if present) is automatically included in the API request to provide context for improved image generation quality.

### Bulk Operations Summary

The module supports bulk operations for content and image generation from the product and category list pages:

**Product List Bulk Actions:**
- "Generate Content (AI)": Generate descriptions for multiple products simultaneously
- "Generate Images (AI)": Generate images for multiple products with the same prompt

**Category List Bulk Actions:**
- "Generate Content (AI)": Generate descriptions for multiple categories simultaneously (new in v1.8.17)

These bulk operations use the same asynchronous GenerationJob pattern as single-product operations, ensuring no HTTP timeouts regardless of how many items are processed.

### Generating Category Descriptions

Category descriptions can be generated directly from the category edit form or through the centralized admin interface (new in v1.8.17).

**From Category Edit Form (Inline Generation):**
1. Navigate to a category edit page
2. Locate the "Description" or "Additional description" fields
3. Click the "Generate Content (AI)" button to generate content using AI
4. The generated content appears as a pending item in a modal
5. Review the generated description and additional description
6. Click "Insert" to apply the content to the category's description or additional description fields

**From All Category Contents Menu:**
1. Go to **Configurer** → **ITR Blue Boost** → **All Category Contents**
2. View all generated category descriptions in a paginated list or grid view
3. Filter by status: pending, accepted, or rejected
4. For pending contents:
   - **Accept**: Click the accept button to apply the generated content to the category
   - **Reject**: Click the reject button and optionally provide a rejection reason
5. For accepted contents:
   - **Delete**: Remove contents you no longer need
6. Use bulk operations to manage multiple contents at once

**Content Type Mapping:**
- **Description**: Maps to category `description` field
- **Additional description**: Maps to category `additional_description` field
- **Meta title**: Maps to category `meta_title` field (new in v1.8.18)
- **Meta description**: Maps to category `meta_description` field (new in v1.8.18)
- **Meta keywords**: Maps to category `meta_keywords` field (new in v1.8.18)

**SEO Fields (v1.8.18):**
The API now returns SEO fields alongside descriptions. On the edit form, each content type (description, additional description, SEO) has its own checkbox to selectively apply fields to the category. Original meta values are displayed for comparison below each SEO field.

### Category FAQs (Read-Only from API)

Starting with v2.0.0, category FAQs are now fetched read-only from the external API and cached locally for optimal performance. Admin-side FAQ generation and management have been removed.

**How Category FAQs Work:**

1. **Front-Office Display**: Category FAQs are automatically displayed in category page footers via the `displayFooterCategory` hook
2. **API Fetching**: When a category page is viewed, FAQs are fetched from the API endpoint `/api/faq/list?type=category&id_category={id}&lang={lang}`
3. **File-Based Caching**: Fetched FAQs are cached in the file system (`var/cache/faq/`) for subsequent accesses
4. **Cache TTL**: Cache entries expire after the configured TTL (default: 3600 seconds = 1 hour)
5. **Language Support**: FAQs are fetched in the current front-office language automatically

### Front-Office Display

Generated content and FAQs automatically appear on the front-office:
- **Product Descriptions**: Displayed as part of the product description when accepted
- **Product FAQs**: Fetched from the API and displayed in the product page extra content section (v2.0.0: read-only from API with file-based caching)
- **Category Descriptions**: Displayed as part of the category description when accepted (new in v1.8.17)
- **Category FAQs**: Fetched from the API and displayed in the category page footer (v2.0.0: read-only from API with file-based caching)

Customers can view the generated content and FAQs without any additional configuration. For descriptions, only accepted and active content is displayed on the front-office. For FAQs, all API-fetched content is displayed automatically.

#### Bootstrap Version Compatibility

The front-office FAQ templates automatically adapt their HTML markup and styling based on the Bootstrap version selected in the **Compatibility settings**:

**Bootstrap 5** (default):
- Uses modern Bootstrap 5 accordion markup with `accordion-*` classes
- Applies `data-bs-toggle="collapse"`, `data-bs-target`, and `data-bs-parent` attributes
- Renders as: `<div class="accordion">` → `<div class="accordion-item">` → `<button class="accordion-button">`

**Bootstrap 4 / Bootstrap 4 Alpha**:
- Uses Bootstrap 4 card-based collapse markup with `card-*` classes
- Applies `data-toggle="collapse"`, `data-target`, and `data-parent` attributes
- Renders as: `<div role="tablist">` → `<div class="card">` → `<a class="itrblueboost-faq-link">`

The `bootstrap_version` variable is automatically passed from the hooks (`hookDisplayProductExtraContent` and `hookDisplayFooterCategory`) to the Smarty templates, ensuring FAQs display with correct styling and functionality regardless of your theme's Bootstrap version. No additional configuration is required beyond selecting the correct Bootstrap version in the Compatibility settings.

### Content Listing Badges

The module automatically displays visual badges in admin product and category listing pages, providing a quick overview of AI-generated content for each item.

**Product Listing Badges:**
- **Images** (grey `badge-secondary`): Shows the count of generated images
- **Contenu** (green `badge-success`): Shows the count of generated content items (descriptions)

**Category Listing Badges:**
- **Contenu** (green `badge-success`): Shows the count of generated content items (descriptions) (new in v1.8.17)

**How It Works:**
1. On product/category listing pages, the module fetches counts for all visible items in a single batch AJAX call
2. Badges are injected dynamically next to the product/category name column
3. Badges update automatically when the listing is reloaded (pagination, filters, sorting)
4. Badges are only shown for items that have at least one item in any category

This feature requires no configuration — it appears automatically when at least one AI service (Images or Content) is enabled.

## Database Tables

The module creates the following database tables:

- `itrblueboost_product_content`: Product description and short description content data
- `itrblueboost_product_content_lang`: Product content in different languages
- `itrblueboost_product_content_shop`: Product content to shop associations
- `itrblueboost_category_content`: Category description content data (new in v1.8.17)
- `itrblueboost_category_content_lang`: Category content in different languages (new in v1.8.17)
- `itrblueboost_category_content_shop`: Category content to shop associations (new in v1.8.17)
- `itrblueboost_product_image`: Generated product images metadata
- `itrblueboost_product_image_shop`: Image to shop associations
- `itrblueboost_generation_job`: Tracks async generation job status, progress, and errors
- `itrblueboost_generation_job_shop`: Generation job to shop associations
- `itrblueboost_api_log`: Complete log of all API requests
- `itrblueboost_credit_history`: History of API credit usage

**Note (v2.0.0):** FAQ tables have been removed as FAQs are now fetched read-only from the external API. Upgrade to v2.0.0 automatically drops the following tables if they exist:
- `itrblueboost_product_faq` (and its _lang, _shop variants)
- `itrblueboost_category_faq` (and its _lang, _shop variants)

## Async Image Generation

Starting with version 1.7.0, image generation is fully asynchronous to prevent HTTP 504 timeout errors. The process works as follows:

### How It Works

1. **Immediate Response**: When a user requests image generation, a `GenerationJob` record is created and returned immediately (with job ID)
2. **Background Processing**: A Symfony command (`itrblueboost:process-generation-job`) processes the job separately from the web request
3. **Progress Tracking**: The job status updates through distinct phases:
   - **Start**: Job created and queued
   - **API Call**: Contacting ITROOM API
   - **Generation**: API processing the image
   - **Save**: Storing generated image in PrestaShop
   - **Done**: Job completed successfully
4. **Frontend Polling**: The admin interface automatically polls for status updates every 2 seconds
5. **Automatic Fallback**: If command execution is unavailable, the system automatically falls back to inline processing using `fastcgi_finish_request()`
6. **Complete API Logging**: All image generation API calls are logged in the API logs section (see **API Logging** below)

### Progress Display

During single-product generation, users see an animated progress bar displaying:
- Overall progress percentage (0-100%)
- Current step with human-readable status label
- Animated shimmer effect for visual feedback

For bulk image generation on the product list, users see:
- Real-time progress bar showing completion percentage
- Current operation status (e.g., "Creating generation job", "Starting image generation")
- Results displayed after completion showing which products had images generated successfully
- Direct links to each product's image management page for quick verification

### Job Status States

- **pending**: Job created, waiting to be processed
- **processing**: Job is currently being processed
- **completed**: Job finished successfully with generated image
- **failed**: Job encountered an error; error message is stored

### Fallback Mechanism

If the Symfony command cannot be executed via `exec()` or `bin/console` is unavailable:
1. The system detects the execution failure
2. It automatically switches to inline processing using `fastcgi_finish_request()`
3. The web request returns immediately, but processing continues on the server

## Performance Optimization

### Efficient Credit Badge Display

The credits badge displayed in the back office header is now highly optimized:

- **No API calls on page loads**: Previously, the badge was refreshed with an API call on every admin page load
- **Configuration-based storage**: The remaining credits are stored in `ps_configuration` with the key `ITRBLUEBOOST_CREDITS_REMAINING`
- **Automatic updates**: The credit balance is automatically updated after each API interaction:
  - FAQ generation (products and categories)
  - Image generation
  - Account info fetch
- **Result**: Significantly reduced API calls and improved back office performance

The credit value is retrieved from the database configuration table on every page load, eliminating unnecessary API requests while keeping the badge always up-to-date after each operation.

### Credit Balance Validation

Starting with version 1.8.1, the module performs automatic credit balance checks in all AI generation modals. This prevents users from attempting generations that would fail due to insufficient credits.

**How It Works:**
- When any AI generation modal opens (FAQ, content, or images), a credit balance check is performed immediately
- The remaining credits are read from the PrestaShop Configuration table (`ITRBLUEBOOST_CREDITS_REMAINING`)
- If remaining credits are 0 or less, a warning message is displayed: "Insufficient credits. Please recharge your credits to use AI generation."
- The Generate button is automatically disabled when credits are insufficient

**Applies To:**
- Product FAQ generation modal
- Category FAQ generation modal
- Product Content generation modal
- Product Image generation modal
- Bulk FAQ generation on product list
- Bulk FAQ generation on category list
- Bulk Image generation on product list

This feature ensures a better user experience by preventing failed API calls and providing clear feedback about credit status before attempting generation.

## API Logging

Starting with version 1.7.0, all API interactions with the ITROOM API are comprehensively logged and visible in the API logs section.

### What Gets Logged

The following API calls are automatically logged with full request/response details:

- **FAQ Generation**: Product and category FAQ generation requests
- **Image Generation**: Product image generation requests (with 300-second timeout for longer processing)
- **Product Content**: Product description and short description generation
- **Account Info**: API account information queries
- **API Updates**: FAQ and content status updates (accept, reject, toggle)

### Log Details

Each API log entry includes:
- **Request Method**: HTTP method (GET, POST, PUT, DELETE)
- **Endpoint**: API endpoint URL
- **Request Body**: Sent parameters and data
- **Response Status**: HTTP response code
- **Response Body**: Complete API response
- **Duration**: Execution time in seconds
- **Error Messages**: Detailed error information if the request failed
- **Context**: Operation type (product_faq, category_faq, image, content, account, etc.)

### Accessing Logs

Navigate to **Configurer** → **ITR Blue Boost** → **API Logs** to view:
- Real-time log entries sorted by timestamp
- Filter and search capabilities for troubleshooting
- Complete request/response inspection for debugging

## Hooks

The module registers the following PrestaShop hooks:

- `actionAdminControllerSetMedia`: Load JS/CSS assets on admin pages
- `displayProductExtraContent`: Fetch and display product FAQs from API on front-office (v2.0.0: now API-based with caching)
- `actionProductDelete`: Clean up images/contents when product is deleted
- `actionObjectImageDeleteAfter`: Update AI image records when PrestaShop images are deleted
- `displayFooterCategory`: Fetch and display category FAQs from API on front-office (v2.0.0: now API-based with caching)
- `actionCategoryDelete`: Clean up contents when category is deleted
- `displayBackOfficeHeader`: Display credit badge in admin header (optimized with Configuration storage)
- `actionMicrodataProduct`: Provide AI-generated product description to itrmicrodata for Product JSON-LD
- `actionMicrodataProductList`: Provide AI-generated descriptions for product listing JSON-LD
- `actionMicrodataProductListPreload`: Batch preload product content before listing loop to avoid N+1 queries
- `actionClearCache`: Clear module caches (FAQ file cache and update check cache) when PrestaShop cache is cleared (new in v2.0.2)
- `displayCMSDisputeInformation`: Fetch and display CMS page FAQs from API on front-office (new in v2.0.3)

## Compatibility

- **PrestaShop Versions**: 1.7.8.2 to 9.x
- **PHP Versions**: 7.1+ (tested on 7.1, 7.2, 7.3, 7.4, 8.0, 8.1, 8.2)
- **Multi-shop**: Fully supported
- **Multisite**: Fully supported

## Changelog

### Version 2.0.0 - Major Release
- **Breaking Change**: All FAQ generation and management removed from admin interface
- **FAQ Management**: Product and category FAQs are now fetched read-only from the external API (`/api/faq/list`)
- **FAQ Caching**: Implemented file-based FAQ caching with configurable TTL to improve performance and reduce API calls
- **Configuration Key**: New `ITRBLUEBOOST_FAQ_CACHE_TTL` setting (default: 3600 seconds)
- **Cache Directory**: FAQs cached in `var/cache/faq/` directory
- **Frontend Unchanged**: Front-office FAQ display on product pages and category footers remains fully functional
- **Removed Features**:
  - All FAQ admin controllers (viewing, editing, generating, deleting FAQs)
  - FAQ admin menu items: "All Product FAQs" and "All Category FAQs"
  - Bulk FAQ generation actions from product/category listing pages
  - FAQ CRUD operations from product/category edit pages
  - FAQ database tables (v2.0.0 upgrade automatically drops them)
- **Removed Files**: 23 files removed including FAQ admin controllers, entities, and CRUD-related classes
- **New Components**:
  - `FaqApiService`: Service class for fetching FAQs from API with file-based caching
  - `DisplayProductExtraContent` hook class: Renders product FAQs from API on product pages
  - `DisplayFooterCategory` hook class: Renders category FAQs from API in category page footers
- **Schema Changes**: Dropped all FAQ-related database tables (see **Database Tables** section)
- **Migration**: v2.0.0 upgrade script automatically cleans up FAQ admin tabs and tables

### Version 1.8.22
- **Bugfix**: Fixed category FAQ button not displaying on PrestaShop 8.1+ and 9.x (incompatible DOM selectors for Symfony category form)
- **Bugfix**: Added legacy category page URL detection for PrestaShop 1.7.x compatibility
- **Enhancement**: Rewrote `admin-category-toolbar.js` with robust multi-version DOM selectors (PS 1.7, 8.0, 8.1+, 9.x)

### Version 1.8.21
- **Bugfix**: Fixed product/category list page detection regex that incorrectly excluded URLs with pagination parameters (e.g., `/products/0/20/name_category/asc`)
- **Bugfix**: Improved re-injection of bulk action buttons after grid AJAX reloads (filtering, sorting, pagination)
- **Bugfix**: Improved re-injection of count badges after grid AJAX reloads
- **Enhancement**: Replaced JavaScript flag-based reload detection with actual DOM presence detection
- **Enhancement**: Added PrestaShop grid event listeners (filter, sort, pagination, URL change) for reliable AJAX reload detection

### Version 1.8.20
- **Automatic Webservice Key**: Creates a PrestaShop webservice key at install/upgrade with full permissions on all resources
- **API Sync**: Syncs webservice key with ITROOM API via PUT /api/webservice
- **Automatic Key Deletion**: Webservice key is deleted on module uninstall
- **HTTP Method Support**: Added PUT/PATCH method support in ApiService
- **New Service**: `WebserviceKeyManager` for managing webservice key lifecycle

### Version 1.8.19
- **itrmicrodata Integration**: Hook into itrmicrodata module to provide AI-generated product descriptions for JSON-LD structured data
- **New Hook**: `actionMicrodataProduct` provides accepted product description for Product JSON-LD on product pages
- **New Hook**: `actionMicrodataProductList` provides product descriptions for ItemList JSON-LD on listing pages
- **New Hook**: `actionMicrodataProductListPreload` batch preloads product content in a single SQL query before the listing loop
- **New Hook Classes**: `ActionMicrodataProduct`, `ActionMicrodataProductList`, `ActionMicrodataProductListPreload` in `src/Hooks/`

### Version 1.8.17 (Unreleased)
- **New Feature**: AI content generation for categories (descriptions and additional descriptions)
- **New Entity**: `CategoryContent` for managing category descriptions with multilang and multishop support
- **SEO Fields**: API returns `meta_title`, `meta_description`, `meta_keywords` alongside descriptions; stored in `_lang` table with original values in main table (v1.8.18)
- **New API Endpoint**: POST `/api/description` with `type: "category"` for category content generation
- **New Controller**: `CategoryContentController` for inline category content generation from category edit page
- **New Controller**: `AllCategoryContentsController` for global category content management with grid/list view toggle
- **New Configuration Key**: `ITRBLUEBOOST_ENABLE_CATEGORY_CONTENT` for enabling/disabling category content generation
- **New Admin Menu**: "All Category Contents" sub-menu for centralized category content management
- **Inline Generation**: "Generate Content (AI)" button on category edit page (same as product)
- **Bulk Operations**: "Generate Content (AI)" bulk action on category listing page
- **Content Badges**: Category listing now displays content count badges alongside FAQ badges
- **Database Tables**: New tables `itrblueboost_category_content`, `itrblueboost_category_content_lang`, `itrblueboost_category_content_shop`
- **Content Type Mapping**:
  - `generated_content` → category `description` field
  - `generated_content_short` → category `additional_description` field
- **Grid/List Views**: All Category Contents page supports grid and list view toggle with view preference persistence
- **Bulk Actions**: Accept All, Reject All, Delete All operations for category contents with floating toolbar
- **Status Management**: Category contents support pending, accepted, rejected status workflow
- **Inline Insertion**: "Insert" button in generation modal to apply content to category description fields

### Version 1.8.8
- **Enhancement**: Bulk image generation now automatically sends product cover images to the API for improved generation results
- This brings parity between bulk and single-product image generation workflows

### Version 1.8.7
- **Bugfix**: Fixed image overflow in the AI image generation modal (base image selector)
- **Bugfix**: Fixed CSS asset paths in admin templates
- **Bugfix**: Fixed original image URL sent to API for image generation with base image
- **Bugfix**: Added missing stylesheet block in product image template

### Version 1.8.5
- **Major Refactoring**: Unified all 16+ modal dialogs across the module with consistent architectural patterns
- **Modal Unification**: Created reusable Twig partials for modals:
  - `_reject_modal.html.twig`: Configurable rejection reason modal with optional warning messages and hidden form fields
  - `_generate_modal.html.twig`: Configurable generation modal supporting content type selection, prompt selection, base image selection, and progress display
- **Bootstrap 4 Native API**: All modals now use Bootstrap 4 native modal API (data-dismiss, data-toggle) instead of custom vanilla JS show/hide
- **Consistent Modal IDs**: Standardized modal element IDs across entire module with configurable options
- **Modal Styling**: Added `.itrblueboost-modal` CSS marker class for unified styling in `admin-common.css`
- **Translation Keys**: All hardcoded text in modals and JavaScript files replaced with translation keys via `Media::addJsDef()`
- **Consistent Headers**: All modal headers now use consistent styling with icon and title (no more gradient vs plain inconsistency)
- **Centralized CSS**: All modal styling and utilities moved to `admin-common.css` (no more inline styles in templates)
- **Code Deduplication**: Eliminated approximately 2,300 lines of duplicated code through trait extraction and partial reuse
- **FAQ Text Truncation**: Removed all FAQ text truncation (slice + ellipsis) in admin views to display full content
- **Controller Traits**: Created 5 traits for shared admin controller functionality:
  - `ResolveLimitTrait`: Pagination limit validation (10, 20, 50, 100 items)
  - `MultilangHelperTrait`: Multilingual text handling for entity fields
  - `FaqApiSyncTrait`: FAQ API synchronization (accept, reject, toggle)
  - `ContentApiSyncTrait`: Product content API synchronization
  - `ProductDataBuilderTrait`: Product data building for bulk operations
- **Entity Traits**: Created 2 traits for shared entity functionality:
  - `FaqStatusTrait`: Common status checking methods (isPending, isAccepted)
  - `FaqEntityTrait`: Shared FAQ methods including position management
- **Shared Query Builder**: Created `AbstractFaqQueryBuilder` base class providing unified grid query logic for FAQ entities
- **CSS Consolidation**: Merged separate PS17/PS8 CSS button files into single `admin-product-buttons.css`
- **JavaScript Utilities**: Created `admin-bulk-common.js` with shared utility functions for bulk actions and AJAX operations
- **Twig Partials**: Extended partial reuse across all admin templates:
  - `_filter_status.html.twig`: Status filter component
  - `_pagination.html.twig`: Pagination component
  - `_lightbox.html.twig`: Image lightbox component
- **DRY Architecture**: All reject and generate modals are now defined once in Twig partials and included with configurable variables, eliminating inline duplication

### Version 1.8.4
- **New Feature**: Product listing badges showing FAQ, image, and content counts next to product names
- **New Controller**: `ProductListCountsController` with batch SQL queries for efficient count retrieval
- **New Route**: `itrblueboost_admin_product_list_counts` for the badge data endpoint
- **Enhancement**: Content service now included in product list page detection

### Version 1.8.3
- **Compatibility**: Lower minimum PrestaShop version from 1.7.8.11 to 1.7.8.2 for broader compatibility

### Version 1.8.2
- **Bugfix**: Wrap Symfony router calls in try/catch to handle missing routes during module installation
- **Bugfix**: Move credit check variables to IIFE scope for proper state persistence in bulk action JavaScript files
- **Stability**: Improved error handling when Symfony routes are not yet cached (e.g. during fresh module installation)

### Version 1.8.1
- **New Feature**: Credit balance validation in all AI generation modals
- **Credit Check**: Automatic credit balance check when opening FAQ, content, and image generation modals
- **User Feedback**: Warning message displayed when remaining credits are 0 or less
- **Disabled Generation**: Generate button is disabled if insufficient credits to prevent failed API calls
- **Applies To**: Product FAQ, Category FAQ, Product Content, and Product Image generation (individual and bulk)
- **Configuration-based**: Credit balance read from `ITRBLUEBOOST_CREDITS_REMAINING` in Configuration table
- **Database Enhancement**: New `api_image_id` column added to `itrblueboost_product_image` table for better image tracking

### Version 1.8.0
- **New Feature**: Compatibility tab for theme Bootstrap version configuration
- **New Feature**: API mode switching (Production/Test environments)
- **New Feature**: Bulk image generation from product list page
- **New Menu**: "Compatibility" sub-menu under Configurer → ITR Blue Boost
- **Bootstrap Version Selection**: Choose between Bootstrap 4, Bootstrap 4 Alpha, and Bootstrap 5
- **API Mode Selection**: Choose between Production (apitr-sf.itroom.fr) and Test (blueboost.itroom.fr)
- **Default Configuration**: Bootstrap 5 selected by default, Production API mode selected by default
- **Configuration Storage**:
  - Bootstrap version stored as `ITRBLUEBOOST_BOOTSTRAP_VERSION` in Configuration
  - API mode stored as `ITRBLUEBOOST_API_MODE` in Configuration (values: 'prod' or 'test')
- **Form Validation**: Server-side validation of allowed Bootstrap versions and API modes
- **Dynamic URL Resolution**: Both ApiService and ApiLogger dynamically resolve the base URL based on configured API mode
- **Adaptive FAQ Templates**: Front-office FAQ templates (product and category) automatically adapt HTML markup based on Bootstrap version:
  - Bootstrap 5: Uses `accordion-*` classes with `data-bs-toggle/data-bs-target/data-bs-parent` attributes
  - Bootstrap 4 / Bootstrap 4 Alpha: Uses `card-*` classes with `data-toggle/data-target/data-parent` attributes
- **Template Auto-Adaptation**: `bootstrap_version` variable automatically passed from hooks to Smarty templates for proper rendering
- **Bulk Image Generation**: Generate images for multiple products simultaneously with:
  - Modal dialog to select an image prompt
  - Real-time progress tracking with percentage and status updates
  - Asynchronous processing using GenerationJob pattern
  - Results display with direct links to each product's image management page
  - Error handling with detailed error reporting
- **New JavaScript File**: `views/js/admin-product-list-bulk-images.js` for bulk image generation UI and logic
- **New Routes**: `itrblueboost_admin_product_image_bulk_generate` and `itrblueboost_admin_product_image_bulk_process` for bulk workflow
- **Enhanced ProductImageController**: New methods `bulkGenerateAction`, `bulkProcessJobAction`, `processBulkJobInline` for batch processing

### Version 1.7.0
- **Major Feature**: Async image generation to prevent HTTP 504 timeouts
- **Major Feature**: Comprehensive API logging for all API interactions (now visible in API logs section)
- **New Entity**: `GenerationJob` for tracking async operation status and progress
- **New Command**: `itrblueboost:process-generation-job {jobId}` for background processing
- **New UI**: Animated progress bar with step indicators and percentage completion
- **New Functionality**: Real-time status polling every 2 seconds for job progress
- **Image Generation Logging**: All image generation API calls now go through ApiLogger with automatic credit logging
- **Timeout Configuration**: ApiLogger::call() accepts optional $timeout parameter (default 120s, image endpoints use 300s)
- **Fallback Mechanism**: Automatic inline processing with `fastcgi_finish_request()` if command execution unavailable
- **Database**: New `itrblueboost_generation_job` and `itrblueboost_generation_job_shop` tables for job tracking
- **API Logging Refactor**: ProcessGenerationJobCommand and ProductImageController refactored to use ApiLogger instead of custom cURL code
- **Status States**: Support for pending, processing, completed, and failed job states with error messages
- **Architecture**: Created `src/Command/ProcessGenerationJobCommand.php` for Symfony command handler

### Version 1.6.1
- **New Feature**: Flexible view modes for All Product FAQs page (grid and list view)
- **New Feature**: Grid/list view preference persisted to browser localStorage
- **New Feature**: Bulk actions with floating toolbar for Accept All, Reject All, and Delete All operations
- **New Feature**: Multi-select with synchronized checkboxes across grid and list views
- **UI Enhancement**: Visual feedback for selected items (highlighted cards/rows)
- **UI Enhancement**: Floating bulk action toolbar appears when items are selected with operation counters
- **UX Improvement**: All checkbox selections remain synchronized when switching between views

### Version 1.6.0
- **New Feature**: AI-powered product description generation (description and short description)
- **New Admin Menu**: "All Product Contents" for centralized management of generated descriptions
- **Inline Generation Buttons**: Generate descriptions directly from product edit form with action buttons
- **Content Workflow**: Automatic content application to products upon acceptance (pending -> accept -> applied)
- **Content Status Management**: Support for pending, accepted, and rejected content states
- **Rejection Tracking**: Capture and send rejection reasons to the API
- **Toggle Content Active Status**: Accepted content can be toggled between active/inactive states
- **Multi-language Support**: All product descriptions are multilingual compatible
- **Shop-aware Content**: Product contents are properly associated with shops in multi-shop installations
- Created new entity class `src/Entity/ProductContent.php` for managing product descriptions
- Implemented controller classes for All Product Contents management: `src/Controller/Admin/AllProductContentsController.php`
- Added database tables: `itrblueboost_product_content`, `itrblueboost_product_content_lang`, `itrblueboost_product_content_shop`

### Version 1.5.0
- **Performance improvement**: Eliminated API call on every admin page load for credit badge display
- Implemented credit balance caching in `ps_configuration` (key: `ITRBLUEBOOST_CREDITS_REMAINING`)
- Credit value is automatically updated after each API interaction (FAQ generation, image generation, account info fetch)
- Created new hook handler class `src/Hooks/DisplayBackOfficeHeader.php` following single-responsibility pattern
- Back office header now displays credits from database configuration instead of making API requests

### Version 1.4.1
- Fixed PrestaShop 1.7.x compatibility issue with DataColumn namespace
- Implemented dynamic class aliasing for DataColumn, ActionColumn, and BulkActionColumn to support both:
  - PrestaShop 8.x: `PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn`
  - PrestaShop 1.7.x: `PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn`
- Grid definitions now work seamlessly across PrestaShop 1.7.8.2 to 9.x

### Version 1.4.0
- Aligned "All Product FAQs" page with "All Category FAQs" page for consistent UI/UX
- Changed FAQ filtering from active/inactive status to pending/accepted/rejected status
- Added Accept/Reject action buttons for pending FAQs
- Added modal dialog for entering rejection reason when rejecting FAQs
- Rejection reason is now sent to API during rejection process
- Implemented API synchronization for accept/reject/toggle actions on product FAQs

### Version 1.3.9
- Fixed admin menu positioning: moved from "Modules" to "Configurer" (Configure) section
- Implemented dropdown menu structure with sub-menus
- Added upgrade script to create missing admin tabs and correct existing tab hierarchy
- Enhanced menu organization for better user experience

### Version 1.3.0 and earlier
- Initial release with core functionality
- Product FAQ generation
- Category FAQ generation
- AI image generation
- Admin interface
- Front-office display

## Development

### Project Structure

```
itrblueboost/
├── src/
│   ├── Install/        # Installation and database setup
│   ├── Controller/     # Admin and API controllers
│   │   └── Traits/     # Shared controller functionality traits
│   ├── Entity/         # Entity models (ProductFaq, CategoryFaq, ProductImage)
│   │   └── Traits/     # Shared entity trait methods
│   ├── Grid/           # Grid definitions and query builders
│   │   ├── Definition/ # Grid definition factories
│   │   ├── Data/       # Grid data factories
│   │   └── Query/      # Query builders (with AbstractFaqQueryBuilder base)
│   ├── Hooks/          # Hook handler classes (one class per hook)
│   ├── Repository/     # Data repository classes
│   ├── Service/        # Business logic services
│   └── Command/        # Symfony console commands
├── views/
│   ├── js/            # JavaScript files (including shared utilities)
│   ├── css/           # Stylesheets (including shared styles)
│   └── templates/
│       ├── admin/     # Admin templates
│       │   └── _partials/  # Shared Twig partials
│       └── front/     # Front-office templates
├── upgrade/           # Version upgrade scripts
├── config/            # Symfony configuration
└── itrblueboost.php   # Main module class
```

### Controller Traits

Shared functionality across multiple admin controllers is implemented using traits in `src/Controller/Admin/Traits/`:

- **ResolveLimitTrait**: Pagination limit validation and resolution (10, 20, 50, 100 items per page)
- **MultilangHelperTrait**: Multilingual text handling for entity fields
- **FaqApiSyncTrait**: FAQ API synchronization (accept, reject, toggle status operations)
- **ContentApiSyncTrait**: Product content API synchronization for descriptions
- **ProductDataBuilderTrait**: Product data building for bulk operations and form population

These traits eliminate code duplication across controllers managing different FAQ types and content.

### Entity Traits

Shared methods for entity models are implemented in `src/Entity/Traits/`:

- **FaqStatusTrait**: Common status checking methods (`isPending()`, `isAccepted()`) for FAQ entities
- **FaqEntityTrait**: Shared FAQ entity methods including `hasApiFaqId()` and `updatePositions()` for position management

### Query Builders

The `src/Grid/Query/AbstractFaqQueryBuilder` base class provides shared grid query logic for FAQ entities:

- Unified table joining and filtering logic
- Common search criteria application
- Support for language and shop context filtering
- Concrete implementations: `ProductFaqQueryBuilder`, `CategoryFaqQueryBuilder`

Subclasses define their table specifics via abstract methods (`getTableName()`, `getPrimaryKey()`, `getSelectColumns()`, `getFilterDefinitions()`).

### Shared Assets

**Stylesheets** (in `views/css/`):
- `admin-common.css`: Centralized admin styling including:
  - Modal styling with `.itrblueboost-modal` class
  - Base image selector styles
  - Progress bar and step indicator styles
  - Form control and button styles shared across all pages
  - Badge and status indicator styling
- `admin-product-buttons.css`: Unified button styling compatible with both PrestaShop 1.7 and 8 (consolidated from separate PS17/PS8 files)
- `admin-product-list-bulk.css`: Product list bulk operation UI styling

**JavaScript** (in `views/js/`):
- `admin-bulk-common.js`: Shared utility functions including:
  - Bulk action checkbox management and synchronization
  - Progress tracking and status polling
  - AJAX operation handlers
  - Modal state management
  - Credit balance checking
- Multiple feature-specific files import and use these shared utilities
- All hardcoded text replaced with translation keys injected via `Media::addJsDef()`

**Twig Partials** (in `views/templates/admin/_partials/`):
- `_filter_status.html.twig`: Reusable status filter component with configurable label and class
- `_pagination.html.twig`: Reusable pagination component
- `_reject_modal.html.twig`: Reusable rejection reason modal (configurable title, modal ID, optional warning message, and hidden form fields)
- `_generate_modal.html.twig`: Reusable generation modal (configurable title, prompt selection, content type selection, base image selector, and progress display options)
- `_lightbox.html.twig`: Reusable lightbox for image display

**Modal Architecture**:
- All modals inherit consistent Bootstrap 4 structure with native API support
- Modal CSS class `.itrblueboost-modal` enables unified styling in `admin-common.css`
- Modal headers feature consistent icons (cancel for reject, auto_awesome for generate)
- All hardcoded text replaced with translation keys via `Media::addJsDef()` for better maintainability
- Modals use Bootstrap 4 native attributes (`data-toggle="modal"`, `data-dismiss="modal"`) instead of custom JavaScript
- Previously 16+ modal implementations across templates unified into 2 reusable partials included with configurable variables

### Hook Handler Architecture

The module follows a single-responsibility pattern for hook handling:

- Each hook has a dedicated handler class in `src/Hooks/`
- Hook handler classes are instantiated by the main module class
- Each handler implements its own logic with a single `execute()` method
- Example: `DisplayBackOfficeHeader.php` handles the `displayBackOfficeHeader` hook for rendering the credits badge
- Example: `ActionMicrodataProduct.php`, `ActionMicrodataProductList.php`, `ActionMicrodataProductListPreload.php` handle integration with the itrmicrodata module

### Code Standards

The module follows:
- PSR-12 coding standards
- Strict type declarations
- Early returns and minimal nesting
- Cyclomatic complexity < 10 per method
- Trait-based composition for shared functionality
- DRY (Don't Repeat Yourself) principle enforced through traits and shared components

## Support

For issues, feature requests, or support, contact ITROOM.

## License

Proprietary - ITROOM

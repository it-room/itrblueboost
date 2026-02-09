# ITR Blue Boost - PrestaShop Module

Integration module with ITROOM API for intelligent data synchronization with PrestaShop.

## Overview

ITR Blue Boost is a PrestaShop module that seamlessly integrates with the ITROOM API to provide:
- AI-powered FAQ generation for products and categories
- Intelligent product image generation
- Complete admin interface for managing generated content
- Real-time credit tracking

## Features

- **Product Description Generation**: Generate AI-powered product descriptions and short descriptions
- **Product FAQ Generation**: Generate frequently asked questions for products using AI
- **Category FAQ Generation**: Create FAQs at the category level
- **AI Image Generation**: Generate product images using ITROOM API
- **Inline Content Generation**: Generate descriptions directly from product edit form with inline buttons
- **Bulk Operations**: Generate content in bulk for multiple products/categories; perform Accept All, Reject All, and Delete All operations on FAQs
- **Flexible FAQ View Modes**: Toggle between grid view (cards) and list view (table) with automatic preference persistence
- **Checkbox Selection System**: Select multiple FAQs across grid/list views with synchronized checkboxes and visual feedback
- **Floating Bulk Toolbar**: Context-aware floating action toolbar appears when items are selected for quick bulk operations
- **Admin Dashboard**: Complete management interface for all generated content
- **Credit System**: Track remaining API credits directly from admin header
- **Multi-shop Support**: Compatible with multi-shop PrestaShop installations
- **Language Support**: Support for all PrestaShop languages
- **Modern Admin UI**: Symfony-based modern admin controllers
- **Front-office Display**: Automatically displays generated FAQs on product and category pages

## Requirements

- PrestaShop: 1.7.8.11 to 8.99.99
- PHP: 7.1 or higher
- ITROOM API Key (required for functionality)

## Installation

1. Download and extract the module to `modules/itrblueboost/`
2. In PrestaShop admin, navigate to: **Modules** → **Module Manager**
3. Search for "ITR Blue Boost" and click **Install**
4. The module will automatically create database tables and admin menu items

After installation, the module creates:
- Database tables for storing FAQs and generated images
- Admin menu in the "Configurer" (Configure) section with sub-menus
- Default configuration values

## Configuration

Once installed, configure the module:

1. Go to **Configurer** → **ITR Blue Boost** → **Settings** in the admin menu
2. Enter your ITROOM API Key
3. Enable desired services:
   - Product FAQ generation
   - Product image generation
   - Category FAQ generation

The remaining API credits are displayed as a badge in the admin header (see **Performance Optimization** below).

### Configuration Keys

The module uses the following configuration keys (stored in `ps_configuration`):

- `ITRBLUEBOOST_API_KEY`: Your ITROOM API key (required)
- `ITRBLUEBOOST_ENABLE_PRODUCT_FAQ`: Enable/disable product FAQ generation
- `ITRBLUEBOOST_ENABLE_PRODUCT_IMAGE`: Enable/disable product image generation
- `ITRBLUEBOOST_ENABLE_CATEGORY_FAQ`: Enable/disable category FAQ generation
- `ITRBLUEBOOST_CREDITS_REMAINING`: Stores the last known remaining API credits (automatically updated)

## Admin Menu Structure

The module creates a dropdown menu in the **Configurer** section with the following sub-menus:

- **Settings**: Configure API key and enable/disable services
- **All Product Contents**: Centralized view for managing all AI-generated product descriptions and short descriptions
- **All generated images**: View and manage all AI-generated product images
- **All product FAQs**: Browse and edit all generated product FAQs
- **All category FAQs**: Browse and edit all generated category FAQs

Additional contextual tabs are automatically displayed:
- Product FAQs tab on product edit page (when enabled)
- AI Images tab on product edit page (when enabled)
- Category FAQs tab on category edit page (when enabled)
- Generate buttons next to description and short description fields on product edit page (when enabled)

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

### Generating Product FAQs

**Individual Product:**
1. Navigate to a product edit page
2. Look for the "FAQ" tab with the count of existing FAQs
3. Click to open FAQ management interface
4. Use the "Generate FAQ (AI)" button to create new FAQs

**Bulk Operations:**
1. Go to product list view
2. Select multiple products
3. Click "Generate FAQ (AI)" from bulk actions
4. Confirm to generate FAQs for all selected products

### Managing Product FAQs - All Product FAQs Page

The "All Product FAQs" admin page provides comprehensive FAQ management with flexible viewing options and bulk operations.

**View Preferences:**
- **Grid View** (default): Display FAQs as cards with question, answer, and status information
- **List View**: Display FAQs as a table with detailed columns
- View preference is automatically saved to browser localStorage and persists across page reloads

**Filtering and Navigation:**
- Filter FAQs by status: All, Pending, Accepted, or Rejected
- Pagination for browsing large numbers of FAQs
- Search and filter preferences are preserved during navigation

**Bulk Actions:**
Select multiple FAQs using checkboxes to perform batch operations:
1. Click individual checkboxes to select specific FAQs, or
2. Use the "Select All" checkbox in list view to select all FAQs on the current page
3. A floating bulk action toolbar appears at the bottom when items are selected, displaying:
   - Selected count indicator
   - **Accept All**: Approve all selected pending FAQs in one action
   - **Reject All**: Reject all selected pending FAQs with optional rejection reason
   - **Delete All**: Remove all selected FAQs permanently
   - **Deselect All**: Clear all selections

**Checkbox Synchronization:**
- Checkboxes remain synchronized between grid and list views
- Selecting an item in grid view automatically checks the corresponding checkbox in list view and vice versa
- Visual feedback (highlighted cards/rows) shows which items are currently selected

**Single Item Actions:**
For individual FAQs:
- **Accept** (pending status): Approve and apply the FAQ
- **Reject** (pending status): Reject with optional rejection reason sent to the API
- **Toggle Active/Inactive** (accepted status): Enable or disable the FAQ without deleting
- **Edit**: Modify the FAQ content
- **Delete**: Remove the FAQ permanently

### Generating Product Images

**Individual Product:**
1. Navigate to a product edit page
2. Look for the "AI Product Images" tab
3. Use the interface to generate new images with custom prompts

### Generating Category FAQs

**Individual Category:**
1. Navigate to a category edit page
2. Look for the "FAQ" button/tab
3. Click to manage category FAQs
4. Use "Generate FAQ (AI)" to create new content

**Bulk Operations:**
1. Go to category list view
2. Select multiple categories
3. Click "Generate FAQ (AI)" from bulk actions

### Front-Office Display

Generated content automatically appears on the front-office:
- **Product FAQs**: Displayed in the product page extra content section
- **Category FAQs**: Displayed in the category page footer

Customers can view and interact with the generated FAQs without any additional configuration.

## Database Tables

The module creates the following database tables:

- `itrblueboost_product_content`: Product description and short description content data
- `itrblueboost_product_content_lang`: Product content in different languages
- `itrblueboost_product_content_shop`: Product content to shop associations
- `itrblueboost_product_faq`: Product FAQ data
- `itrblueboost_product_faq_lang`: FAQ content in different languages
- `itrblueboost_product_faq_shop`: FAQ to shop associations
- `itrblueboost_product_image`: Generated product images metadata
- `itrblueboost_product_image_shop`: Image to shop associations
- `itrblueboost_category_faq`: Category FAQ data
- `itrblueboost_category_faq_lang`: Category FAQ content by language
- `itrblueboost_category_faq_shop`: Category FAQ to shop associations
- `itrblueboost_api_log`: Complete log of all API requests
- `itrblueboost_credit_history`: History of API credit usage

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

## Hooks

The module registers the following PrestaShop hooks:

- `actionAdminControllerSetMedia`: Load JS/CSS assets on admin pages
- `displayProductExtraContent`: Display product FAQs on front-office
- `actionProductDelete`: Clean up FAQs/images/contents when product is deleted
- `actionObjectImageDeleteAfter`: Update AI image records when PrestaShop images are deleted
- `displayFooterCategory`: Display category FAQs on front-office
- `actionCategoryDelete`: Clean up FAQs when category is deleted
- `displayBackOfficeHeader`: Display credit badge in admin header (optimized with Configuration storage)

## Compatibility

- **PrestaShop Versions**: 1.7.8.11 to 8.99.99
- **PHP Versions**: 7.1+ (tested on 7.1, 7.2, 7.3, 7.4, 8.0, 8.1, 8.2)
- **Multi-shop**: Fully supported
- **Multisite**: Fully supported

## Changelog

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
- Grid definitions now work seamlessly across PrestaShop 1.7.8.11 to 8.99.99

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
│   ├── Entity/         # Entity models (ProductFaq, CategoryFaq, ProductImage)
│   ├── Hooks/          # Hook handler classes (one class per hook)
│   ├── Repository/     # Data repository classes
│   └── Service/        # Business logic services
├── views/
│   ├── js/            # JavaScript files
│   ├── css/           # Stylesheets
│   └── templates/     # Twig/Smarty templates
├── upgrade/           # Version upgrade scripts
├── config/            # Symfony configuration
└── itrblueboost.php   # Main module class
```

### Hook Handler Architecture

The module follows a single-responsibility pattern for hook handling:

- Each hook has a dedicated handler class in `src/Hooks/`
- Hook handler classes are instantiated by the main module class
- Each handler implements its own logic with a single `execute()` method
- Example: `DisplayBackOfficeHeader.php` handles the `displayBackOfficeHeader` hook for rendering the credits badge

### Code Standards

The module follows:
- PSR-12 coding standards
- Strict type declarations
- Early returns and minimal nesting
- Cyclomatic complexity < 10 per method

## Support

For issues, feature requests, or support, contact ITROOM.

## License

Proprietary - ITROOM

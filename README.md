# ITR Blue Boost - PrestaShop Module

Integration module with ITROOM API for intelligent data synchronization with PrestaShop.

## Overview

ITR Blue Boost is a PrestaShop module that seamlessly integrates with the ITROOM API to provide:
- AI-powered FAQ generation for products and categories
- Intelligent product image generation
- Complete admin interface for managing generated content
- Real-time credit tracking

## Features

- **Product FAQ Generation**: Generate frequently asked questions for products using AI
- **Category FAQ Generation**: Create FAQs at the category level
- **AI Image Generation**: Generate product images using ITROOM API
- **Bulk Operations**: Generate content in bulk for multiple products/categories
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

The remaining API credits are displayed as a badge in the admin header.

## Admin Menu Structure

The module creates a dropdown menu in the **Configurer** section with the following sub-menus:

- **Settings**: Configure API key and enable/disable services
- **All generated images**: View and manage all AI-generated product images
- **All product FAQs**: Browse and edit all generated product FAQs
- **All category FAQs**: Browse and edit all generated category FAQs

Additional contextual tabs are automatically displayed:
- Product FAQs tab on product edit page (when enabled)
- AI Images tab on product edit page (when enabled)
- Category FAQs tab on category edit page (when enabled)

## Usage

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

## Hooks

The module registers the following PrestaShop hooks:

- `actionAdminControllerSetMedia`: Load JS/CSS assets on admin pages
- `displayProductExtraContent`: Display product FAQs on front-office
- `actionProductDelete`: Clean up FAQs/images when product is deleted
- `actionObjectImageDeleteAfter`: Update AI image records when PrestaShop images are deleted
- `displayFooterCategory`: Display category FAQs on front-office
- `actionCategoryDelete`: Clean up FAQs when category is deleted
- `displayBackOfficeHeader`: Display credit badge in admin header

## Compatibility

- **PrestaShop Versions**: 1.7.8.11 to 8.99.99
- **PHP Versions**: 7.1+ (tested on 7.1, 7.2, 7.3, 7.4, 8.0, 8.1, 8.2)
- **Multi-shop**: Fully supported
- **Multisite**: Fully supported

## Changelog

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

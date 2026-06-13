<?php

namespace Modules\Documentation\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Documentation\Models\Document;
use Modules\Documentation\Models\DocumentCategory;

class GetStartedDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $category = DocumentCategory::where('slug', 'get-started')->firstOrFail();

        $articles = $this->articles($category->id);

        foreach ($articles as $article) {
            Document::updateOrCreate(
                [
                    'business_id' => 1,
                    'slug'        => $article['slug'],
                ],
                $article,
            );
        }

        $this->command->info('Get Started articles seeded: ' . count($articles));
    }

    private function articles(int $categoryId): array
    {
        return [
            [
                'business_id'          => 1,
                'created_by'           => 1,
                'document_category_id' => $categoryId,
                'title'                => 'How to Set Up Your Zeebroo Account',
                'slug'                 => 'how-to-setup-zeebroo-account',
                'status'               => Document::STATUS_PUBLISHED,
                'content'              => <<<'TEXT'
Welcome to Zeebroo — your all-in-one business management platform. This guide walks you through everything you need to do to get your account ready and start running your business from day one.


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  STEP 1 — Create Your Account
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. Open your browser and go to the Zeebroo website.
2. Click the "Sign Up Free" button in the top-right corner.
3. Enter your full name, email address, and a strong password (minimum 8 characters, mix of letters and numbers).
4. Click "Create Account."
5. Check your email inbox for a verification message and click the confirmation link.

   TIP: Use a business email address (e.g. you@yourbusiness.com) rather than a personal one. This makes it easier to hand over the account if needed and looks more professional to your team.

Once verified, you will be taken to the account setup wizard automatically.


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  STEP 2 — Set Up Your Business Profile
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

After email verification, Zeebroo prompts you to create your first business workspace.

Fill in the following details:

  Business Name        — The official name of your business as it should appear on receipts and reports.
  Business Type        — Select the category that best describes your business (Retail, Food & Beverage, Service, etc.).
  Phone Number         — Your primary business contact number.
  Address              — Your physical business address. Used for receipts and tax documents.
  Currency             — Choose the currency you trade in. This cannot be changed later without contacting support.
  Time Zone            — Set your local time zone so that sales reports and scheduling are accurate.

Click "Save Business" when done. You can update any of these details later from Settings > Business Profile.


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  STEP 3 — Upload Your Logo
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

A logo makes your receipts and customer-facing documents look professional.

1. Go to Settings > Business Profile.
2. Click the logo area or "Change Logo."
3. Upload a PNG or JPG file. Recommended size: 400 × 400 px or larger, square format.
4. Click "Save."

Your logo will appear on printed receipts, invoices, and the customer display screen on the POS.


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  STEP 4 — Add Your Products or Services
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Before you can make a sale, you need products in the system.

  Go to:  Inventory > Products > Add Product

For each product, fill in:

  Name         — Clear, customer-facing product name.
  SKU / Code   — Optional barcode or internal reference code.
  Category     — Group products for easier searching during sales.
  Price        — Selling price (inclusive or exclusive of tax — see Step 5).
  Cost Price   — What you pay for the item. Used for profit margin reports.
  Stock Qty    — Opening stock count.
  Unit         — e.g. "piece," "kg," "litre."
  Image        — Optional but recommended for POS visual search.

Click "Save Product." Repeat for each item.

   BULK IMPORT: If you have many products, use the CSV import option. Download the template from Inventory > Import, fill in the spreadsheet, and upload it. The system will import all products at once.


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  STEP 5 — Configure Tax Settings
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. Go to Settings > Tax.
2. Enable tax if applicable in your region.
3. Enter your tax rate (e.g. 15 for 15% GST/VAT).
4. Choose whether product prices are entered inclusive or exclusive of tax.
5. Save.

Tax will be automatically calculated and shown on receipts and reports.


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  STEP 6 — Set Up Payment Methods
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Go to Settings > Payment Methods and enable the payment types you accept:

  Cash             — Always enabled by default.
  Card / EFTPOS    — Enable and configure your card terminal integration.
  Bank Transfer    — Add your bank account details for customers paying by transfer.
  Credit / Account — Allow trusted customers to purchase on account and pay later.
  Custom           — Add any other method specific to your business (e.g. vouchers, gift cards).

Each payment method will appear as a button on the POS checkout screen.


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  STEP 7 — Add Your Team Members
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

To give your staff access, go to HR > Employees > Add Employee.

For each employee, set:

  Name & Contact   — Full name, phone, and email.
  Job Title / Role — Determines what they can access in the system.
  PIN              — A 4-digit PIN used to log in to the POS terminal.
  Salary / Pay     — For payroll processing (optional at setup).

Role-based access controls what each employee can see and do:

  Cashier          — POS sales only. Cannot view reports or settings.
  Supervisor       — POS sales plus basic reports and discount approval.
  Manager          — Full access except account billing and owner settings.
  Owner            — Full access to everything.

Employees receive a login email invitation and can access their portal at /hr-portal.


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  STEP 8 — Open the POS and Make Your First Sale
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. Click "POS" in the left sidebar or navigate to /pos.
2. The POS opens with your product grid on the right and the cart on the left.
3. Search for a product by name or scan a barcode.
4. Click the product to add it to the cart.
5. Adjust quantity if needed by clicking the item in the cart.
6. Click "Charge" or "Pay" when the customer is ready.
7. Select the payment method.
8. Enter the amount tendered (for cash).
9. Click "Complete Sale."
10. The receipt will print automatically if a printer is connected.

Congratulations — you have completed your first sale on Zeebroo!


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  STEP 9 — Explore Your Dashboard
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

The Dashboard gives you a live snapshot of your business:

  Today's Sales       — Total revenue collected today.
  Transaction Count   — Number of sales completed.
  Top Products        — Best-selling items by quantity and revenue.
  Low Stock Alerts    — Products running low on inventory.
  Gross Profit        — Revenue minus cost of goods sold.
  Staff on Shift      — Who is clocked in right now.

Use the date filter at the top to view daily, weekly, monthly, or custom date ranges.


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  WHAT'S NEXT?
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Now that your account is set up, explore these areas to get the most out of Zeebroo:

  Customers       — Build a customer database and track purchase history.
  Suppliers       — Add suppliers and create purchase orders.
  Reports         — Deep-dive into sales, inventory, and financial reports.
  Accounting      — Connect your chart of accounts and track income & expenses.
  HR & Payroll    — Manage attendance, leaves, and run payroll.
  Loyalty         — Set up a loyalty points program to reward repeat customers.
  Integrations    — Connect social media, e-commerce, and other third-party tools.

If you have questions, visit our FAQ section or contact our support team — we are here to help.
TEXT,
            ],
        ];
    }
}

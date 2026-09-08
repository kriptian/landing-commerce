<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->reconcileStoreColumns();
        $this->reconcileProductColumns();
        $this->reconcileOrderColumns();
        $this->reconcileUserEmailIndex();

        $this->addForeignKeyIfSafe('physical_sales', 'store_id', 'stores', 'cascade');
        $this->addForeignKeyIfSafe('physical_sale_items', 'product_id', 'products', 'cascade');
        $this->addForeignKeyIfSafe('physical_sale_items', 'product_variant_id', 'product_variants', 'cascade');
        $this->addForeignKeyIfSafe('customers', 'store_id', 'stores', 'cascade');
        $this->addForeignKeyIfSafe('coupons', 'store_id', 'stores', 'cascade');
        $this->addForeignKeyIfSafe('coupon_products', 'product_id', 'products', 'cascade');
        $this->addForeignKeyIfSafe('coupon_usages', 'order_id', 'orders', 'set null');
        $this->addForeignKeyIfSafe('customer_notifications', 'order_id', 'orders', 'cascade');
        $this->addForeignKeyIfSafe('gallery_images', 'store_id', 'stores', 'cascade');
        $this->addForeignKeyIfSafe('gallery_images', 'product_id', 'products', 'set null');

        $this->createPhysicalSalesRoles();
    }

    public function down(): void
    {
        // This migration may reconcile schema created by already-deployed migrations.
        // Removing it on rollback must not delete existing columns, constraints, or data.
    }

    private function reconcileStoreColumns(): void
    {
        if (! Schema::hasTable('stores')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'catalog_use_default')) {
                $table->boolean('catalog_use_default')->default(true);
            }
            if (! Schema::hasColumn('stores', 'catalog_button_color')) {
                $table->string('catalog_button_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_promo_banner_color')) {
                $table->string('catalog_promo_banner_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_variant_button_color')) {
                $table->string('catalog_variant_button_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_purchase_button_color')) {
                $table->string('catalog_purchase_button_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_cart_bubble_color')) {
                $table->string('catalog_cart_bubble_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_social_button_color')) {
                $table->string('catalog_social_button_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_logo_position')) {
                $table->string('catalog_logo_position')->default('center');
            }
            if (! Schema::hasColumn('stores', 'catalog_menu_type')) {
                $table->string('catalog_menu_type')->default('hamburger');
            }
            if (! Schema::hasColumn('stores', 'catalog_product_template')) {
                $table->string('catalog_product_template')->default('default');
            }
            if (! Schema::hasColumn('stores', 'catalog_header_style')) {
                $table->string('catalog_header_style')->default('default');
            }
            if (! Schema::hasColumn('stores', 'catalog_header_bg_color')) {
                $table->string('catalog_header_bg_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_header_text_color')) {
                $table->string('catalog_header_text_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_button_bg_color')) {
                $table->string('catalog_button_bg_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_button_text_color')) {
                $table->string('catalog_button_text_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_body_bg_color')) {
                $table->string('catalog_body_bg_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_body_text_color')) {
                $table->string('catalog_body_text_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_input_bg_color')) {
                $table->string('catalog_input_bg_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_input_text_color')) {
                $table->string('catalog_input_text_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_promo_banner_text_color')) {
                $table->string('catalog_promo_banner_text_color')->nullable();
            }
            if (! Schema::hasColumn('stores', 'catalog_show_buy_button')) {
                $table->boolean('catalog_show_buy_button')->default(false);
            }
            if (! Schema::hasColumn('stores', 'gallery_type')) {
                $table->enum('gallery_type', ['products', 'custom'])->default('products');
            }
            if (! Schema::hasColumn('stores', 'gallery_show_buy_button')) {
                $table->boolean('gallery_show_buy_button')->default(true);
            }
        });
    }

    private function reconcileProductColumns(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'barcode')) {
                $table->string('barcode')->nullable();
            }
            if (! Schema::hasColumn('products', 'meta_keywords')) {
                $table->text('meta_keywords')->nullable();
            }
        });
    }

    private function reconcileOrderColumns(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'customer_id')) {
                $table->foreignId('customer_id')->nullable();
            }
            if (! Schema::hasColumn('orders', 'address_id')) {
                $table->foreignId('address_id')->nullable();
            }
            if (! Schema::hasColumn('orders', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable();
            }
            if (! Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0);
            }
        });

        $this->addForeignKeyIfSafe('orders', 'customer_id', 'customers', 'set null');
        $this->addForeignKeyIfSafe('orders', 'address_id', 'addresses', 'set null');
        $this->addForeignKeyIfSafe('orders', 'coupon_id', 'coupons', 'set null');
    }

    private function reconcileUserEmailIndex(): void
    {
        if (! Schema::hasColumn('users', 'store_id')) {
            return;
        }

        if (Schema::hasIndex('users', ['email'], 'unique')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['email']));
        }

        if (! Schema::hasIndex('users', ['store_id', 'email'], 'unique')) {
            Schema::table('users', fn (Blueprint $table) => $table->unique(
                ['store_id', 'email'],
                'users_store_email_unique'
            ));
        }
    }

    private function addForeignKeyIfSafe(
        string $tableName,
        string $column,
        string $referencedTable,
        string $onDelete,
    ): void {
        if (
            ! Schema::hasTable($tableName)
            || ! Schema::hasColumn($tableName, $column)
            || ! Schema::hasTable($referencedTable)
            || $this->hasForeignKey($tableName, $column)
        ) {
            return;
        }

        $hasOrphans = DB::table($tableName)
            ->whereNotNull($column)
            ->whereNotExists(function ($query) use ($tableName, $column, $referencedTable) {
                $query->selectRaw('1')
                    ->from($referencedTable)
                    ->whereColumn("{$referencedTable}.id", "{$tableName}.{$column}");
            })
            ->exists();

        if ($hasOrphans) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column, $referencedTable, $onDelete) {
            $table->foreign($column)->references('id')->on($referencedTable)->onDelete($onDelete);
        });
    }

    private function hasForeignKey(string $tableName, string $column): bool
    {
        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'], true)) {
                return true;
            }
        }

        return false;
    }

    private function createPhysicalSalesRoles(): void
    {
        if (! Schema::hasTable('stores') || ! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'store_id')) {
            return;
        }

        $guard = config('auth.defaults.guard', 'web');

        DB::table('stores')->orderBy('id')->each(function ($store) use ($guard) {
            $exists = DB::table('roles')
                ->where('store_id', $store->id)
                ->where('name', 'physical-sales')
                ->where('guard_name', $guard)
                ->exists();

            if (! $exists) {
                DB::table('roles')->insert([
                    'store_id' => $store->id,
                    'name' => 'physical-sales',
                    'guard_name' => $guard,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
};

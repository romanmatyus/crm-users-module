<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddressChangeRequestsNullableFields extends AbstractMigration
{
    public function up(): void
    {
        $this->table('address_change_requests')
            ->changeColumn('address', 'string', ['null' => true])
            ->changeColumn('city', 'string', ['null' => true])
            ->changeColumn('zip', 'string', ['null' => true])
            ->update();

        $sql = <<<SQL
        UPDATE addresses SET `first_name` = NULL WHERE `first_name` = '';
        UPDATE addresses SET `last_name` = NULL WHERE `last_name` = '';
        UPDATE addresses SET `phone_number` = NULL WHERE `phone_number` = '';
        UPDATE addresses SET `number` = NULL WHERE `number` = '';
        UPDATE addresses SET `address` = NULL WHERE `address` = '';
        UPDATE addresses SET `city` = NULL WHERE `city` = '';
        UPDATE addresses SET `zip` = NULL WHERE `zip` = '';
        UPDATE addresses SET `company_name` = NULL WHERE `company_name` = '';
        UPDATE addresses SET `company_id` = NULL WHERE `company_id` = '';
        UPDATE addresses SET `company_tax_id` = NULL WHERE `company_tax_id` = '';
        UPDATE addresses SET `company_vat_id` = NULL WHERE `company_vat_id` = '';

        UPDATE address_change_requests SET `first_name` = NULL WHERE `first_name` = '';
        UPDATE address_change_requests SET `last_name` = NULL WHERE `last_name` = '';
        UPDATE address_change_requests SET `phone_number` = NULL WHERE `phone_number` = '';
        UPDATE address_change_requests SET `number` = NULL WHERE `number` = '';
        UPDATE address_change_requests SET `address` = NULL WHERE `address` = '';
        UPDATE address_change_requests SET `city` = NULL WHERE `city` = '';
        UPDATE address_change_requests SET `zip` = NULL WHERE `zip` = '';
        UPDATE address_change_requests SET `company_name` = NULL WHERE `company_name` = '';
        UPDATE address_change_requests SET `company_id` = NULL WHERE `company_id` = '';
        UPDATE address_change_requests SET `company_tax_id` = NULL WHERE `company_tax_id` = '';
        UPDATE address_change_requests SET `company_vat_id` = NULL WHERE `company_vat_id` = '';

        UPDATE address_change_requests SET `old_first_name` = NULL WHERE `old_first_name` = '';
        UPDATE address_change_requests SET `old_last_name` = NULL WHERE `old_last_name` = '';
        UPDATE address_change_requests SET `old_phone_number` = NULL WHERE `old_phone_number` = '';
        UPDATE address_change_requests SET `old_number` = NULL WHERE `old_number` = '';
        UPDATE address_change_requests SET `old_address` = NULL WHERE `old_address` = '';
        UPDATE address_change_requests SET `old_city` = NULL WHERE `old_city` = '';
        UPDATE address_change_requests SET `old_zip` = NULL WHERE `old_zip` = '';
        UPDATE address_change_requests SET `old_company_name` = NULL WHERE `old_company_name` = '';
        UPDATE address_change_requests SET `old_company_id` = NULL WHERE `old_company_id` = '';
        UPDATE address_change_requests SET `old_company_tax_id` = NULL WHERE `old_company_tax_id` = '';
        UPDATE address_change_requests SET `old_company_vat_id` = NULL WHERE `old_company_vat_id` = '';
SQL;
        $this->execute($sql);
    }

    public function down(): void
    {

        $sql = <<<SQL
        UPDATE address_change_requests SET `address` = '' WHERE `address` IS NULL;
        UPDATE address_change_requests SET `city` = '' WHERE `city` IS NULL;
        UPDATE address_change_requests SET `zip` = '' WHERE `zip` IS NULL;
SQL;
        $this->execute($sql);

        $this->table('address_change_requests')
            ->changeColumn('address', 'string', ['null' => false])
            ->changeColumn('city', 'string', ['null' => false])
            ->changeColumn('zip', 'string', ['null' => false])
            ->update();
    }
}

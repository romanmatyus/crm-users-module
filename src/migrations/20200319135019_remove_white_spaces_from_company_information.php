<?php

use Phinx\Migration\AbstractMigration;

class RemoveWhiteSpacesFromCompanyInformation extends AbstractMigration
{
    public function change()
    {
        $this->execute("UPDATE address_change_requests SET company_id = REPLACE(company_id, ' ', '') WHERE company_id IS NOT NULL AND company_id != '' AND company_id NOT LIKE 'GDPR%'");
        $this->execute("UPDATE address_change_requests SET company_tax_id = REPLACE(company_tax_id, ' ', '') WHERE company_tax_id IS NOT NULL AND company_tax_id != '' AND company_tax_id NOT LIKE 'GDPR%'");
        $this->execute("UPDATE address_change_requests SET company_vat_id = REPLACE(company_vat_id, ' ', '') WHERE company_vat_id IS NOT NULL AND company_vat_id != '' AND company_vat_id NOT LIKE 'GDPR%'");

        $this->execute("UPDATE address_change_requests SET old_company_id = REPLACE(old_company_id, ' ', '') WHERE old_company_id IS NOT NULL AND old_company_id != '' AND old_company_id NOT LIKE 'GDPR%'");
        $this->execute("UPDATE address_change_requests SET old_company_tax_id = REPLACE(old_company_tax_id, ' ', '') WHERE old_company_tax_id IS NOT NULL AND old_company_tax_id != '' AND old_company_tax_id NOT LIKE 'GDPR%'");
        $this->execute("UPDATE address_change_requests SET old_company_vat_id = REPLACE(old_company_vat_id, ' ', '') WHERE old_company_vat_id IS NOT NULL AND old_company_vat_id != '' AND old_company_vat_id NOT LIKE 'GDPR%'");

        $this->execute("UPDATE addresses SET company_id = REPLACE(company_id, ' ', '') WHERE company_id IS NOT NULL AND company_id != '' AND company_id NOT LIKE 'GDPR%'");
        $this->execute("UPDATE addresses SET company_tax_id = REPLACE(company_tax_id, ' ', '') WHERE company_tax_id IS NOT NULL AND company_tax_id != '' AND company_tax_id NOT LIKE 'GDPR%'");
        $this->execute("UPDATE addresses SET company_vat_id = REPLACE(company_vat_id, ' ', '') WHERE company_vat_id IS NOT NULL AND company_vat_id != '' AND company_vat_id NOT LIKE 'GDPR%'");
    }
}

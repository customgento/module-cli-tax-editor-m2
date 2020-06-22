# CustomGento_CliTaxEditor
Magento 2 module, which provides new console commands to edit the tax configuration.

## Commands

### Edit Rates Of Existing Tax Rates

    $ bin/magento tax:rates:edit [--ids[="..."]] [--rate[="..."]] [--update-titles]

#### Options

`--ids`            Comma-separated list of tax rate IDs.

`--rate`           The new rate.

`--update-titles`  Update the code and the titles of the tax rate as well.

# CLI Tax Editor for Magento 2
CLI Tax Editor for Magento 2 adds a CLI command to update tax rates. This is pretty useful for general tax rate changes in a country, which must be applied exactly at a specific date. It is possible to define a new rate for a range of tax rate IDs.

## Commands

### Edit Rates Of Existing Tax Rates

    $ bin/magento tax:rates:edit [--ids[="..."]] [--rate[="..."]] [--update-titles]

#### Options

`--ids`            Comma-separated list of tax rate IDs

`--rate`           The new rate (integer or float with decimal point)

`--update-titles`  Update the code and the titles of the tax rate as well

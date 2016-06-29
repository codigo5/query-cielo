# Cielo for Magento

Cielo payment gateway for Magento.
p.s. This repository is read-only mode for composer. This module has been developed by [Query Commerce](http://querycommerce.com/).

## Installation

### Via modman

- Install [modman](https://github.com/colinmollenhour/modman)
- Use the command from your Magento installation folder: `modman clone https://github.com/codigo5/query-cielo.git`

### Via composer
- Install [composer](http://getcomposer.org/download/)
- Create a composer.json into your project like the following sample:

```json
{
    ...
    "require": {
      "codigo5/query-cielo": "*"
    },
    "repositories": [
      {
        "type": "vcs",
        "url": "https://github.com/codigo5/query-cielo.git"
      }
    ],
    "extra": {
      "magento-root-dir": "./"
    }
}
```

- Then from your composer.json folder: `php composer.phar install` or `composer install`

### Manually
- You can copy the files from the folders of this repository to the same folders of your installation

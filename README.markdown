Fat Zebra WP e-Commerce Plugin
==============================

Version 1.0.3 for API Version 1.0

A WordPress plugin to add Fat Zebra support to [WP e-Commerce](http://www.getshopped.org) for Australian Merchants.

Dependencies
------------

 * WordPress
 * WP e-Commerce ~> 3.8

This plugin uses wp_http_request to submit data to Fat Zebra - this means that you do not need to ensure cURL or similar is installed on your site, the function will determine the most suitable technique to use.


Install the plugin
---------------------
There are two methods to install the plugin:

**Copy the file to the WordPress plugins directory:**
 
 
 1. Make a new directory in [SITE_ROOT]/wp-content/plugins called wp-e-commerce-fatzebra
 2. Copy the file wp-e-commerce-fatzebra.php to this new directory.
 3. Activate the newly installed plugin in the WordPress Plugin Manager.

**Install the plugin from WordPress**

 1. Search for the *WP e-Commerce Fat Zebra* plugin from the WordPress Plugin Manager.
 2. Install the plugin.
 3. Activate the newly installed plugin.


Configuration
-------------

1. Visit the WP e-Commerce store settings page, and click on the **Payments** tab.
2. Check the **Fat Zebra** opeion and then click on the **edit** link.
3. Add your username and token, and select the test mode settings, then click Update.

You should now be able to test the purchases via Fat Zebra.

Support
-------
If you have any issue with the Fat Zebra Gateway for WP e-Commerce please contact us at support@fatzebra.com.au and we will be more then happy to help out.

Pull Requests
-------------
If you would like to contribute to the plugin please fork the project, make your changes within a feature branch and then submit a pull request. All pull requests will be reviewed as soon as possible and integrated into the main branch if deemed suitable.
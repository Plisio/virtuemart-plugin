VM Payment - Plisio Cryptocurrency Payment Gateway
=====================================================

This plugin allows stores using the VirtueMart shopping cart system to accept cryptocurrency payments via the Plisio gateway.

Read the plugin installation instructions below to get started with Plisio Cryptocurrency payment gateway on your shop.
Accept Bitcoin, Litecoin, Ethereum and other coins.
Full setup guide with screenshots is also available on: <https://plisio.net/virtuemart-accept-crypto>


## Install

Sign up for Plisio account at <https://plisio.net>.

Setup your store in API tab.

### via Extension Manager

1. Download [plg_vmpayment_plisio.zip](https://github.com/Plisio/virtuemart-plugin/releases/download/v4.0.0/plg_vmpayment_plisio.zip)

2. Login to your VirtueMart store admin panel, go to *System » Extensions » Upload Package File*. In the *Upload Package File* part, choose **plg_vmpayment_plisio.zip** you previously downloaded, then click **Upload & Install**.

3. Go to *Manage Extensions*. In search box type **Plisio** and click **Search**. Either click on status indicator located in Plisio extension row, or mark the checkbox of Plisio extension row and click **Enable** at the top of admin panel.

4. Go to *Components » VirtueMart » Payment Methods » New*. Type in the information, selecting **VM Payment - Plisio** as **Payment Method**. Be sure to select **Yes** in the publish section. Click **Save**. Click **Configuration**. Fill in your *API Secret Key* with *Secret key* from Plisio API tab.  **Be sure to set order statuses correctly**. Click **Save & Close**.

## Notes
The plugin version 4.0.0 is designed for Joomla version 4 and VirtueMart version 4, and supports PHP version 8.1. Testing on other configurations has not been conducted, so please report any bugs to Plisio support. Thank you!
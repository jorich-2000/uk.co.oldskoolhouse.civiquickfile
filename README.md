uk.co.oldskoolhouse.civiquickfile
=====================

Synchronisation between CiviCRM &amp; Quickfile Online Accounts (quickfile.co.uk)

This extension requires the extension https://github.com/eileenmcnaughton/nz.co.fuzion.accountsync to work.

It sets up scheduled jobs that synchronize Quickfile contacts and invoices with CiviCRM contacts and invoices.

Interaction with this module is primarily by API and it creates scheduled jobs to run those API. These jobs may not auto-create in CiviCRM versions prior to 4.4 or 4.2.16.

SETUP

In the server in the sites, extensions folder in a terminal window you can run the command 
git clone https://github.com/eileenmcnaughton/nz.co.fuzion.civixero.git 
and the same for account sync
git clone https://github.com/eileenmcnaughton/nz.co.fuzion.accountsync.git
then you will have the extensions added to the site.

To use these extensions on the site, on the Civi menu on the site go to administer - customise data and screens - manage extensions. There you should install CiviQuickfile and Account Sync.

You should now have a Quickfile tab in the civi menu. From here you can edit the Quickfile settings. To do this 

You need a Quickfile api key. 

Log into your personal Quickfile instance https://<account name>.quickfile.co.uk/secure/apps/index.aspx

Choose 'Create Application'

Follow the Quickfile instructions to set up an application name and description

Add the following APi methods
Clinet_Create
Client_Get
Client_Update
Invoise_Create

You will then be able to access the QuickFile App ID  you need for CiviCRM


You then need to enter these keys into the CiviQuickfile Settings page

On this page you should also define which edit and create actions which should trigger contacts / invoices to be created / edited in Quickfile

Once installed you interact with CiviQuickfile via the scheduled jobs page and the api. Matched contacts should show links on their contact summary screen and matched contributions should show links on the invoices

API Documentation is available here:http://api.quickfile.co.uk/

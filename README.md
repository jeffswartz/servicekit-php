# OpenTok Service Kit

An OpenTok Starter Kit for creating a customer service application.

## Installation

1. Clone the repository.

2. Copy the `config/development/opentok.php.sample` file to `config/development/opentok.php` and
   replace the `key` and `secret` settings with your OpenTok API key and secret, from the [TokBox
   Dashboard](https://dashboard.tokbox.com).

3. Copy the `config/development/memcached.php.sample` file to `config/development/memcached.php`
   and replace the `pool` setting to a [Memcached](http://memcached.org/) instance. The format
   for this string is `<host>:<port>`. If the instance requires authentication, you should also
   set the `username` and `password` settings.

4. Use [Composer](https://getcomposer.org/) to install dependencies: `composer install`

5. Set the document root for your web server (such as Apache, nginx, etc.) to the `web` directory
   of this project. In the case of Apache, the provided `web\.htaccess` file handles URL rewriting.
   See the [Slim Route URL Rewriting Guide](http://docs.slimframework.com/#Route-URL-Rewriting)
   for more details.

## Usage

1. Visit the URL mapped to the application by your web server. This will be the customer's page.

2. Click on the "Talk to a Representative" button. Fill in the information needed in the form and
   click the "Request a Representative" button. You will also be asked to allow access to your
   camera and microphone; allow them.

3. Have another user (possibly in another window or tab) visit the `/rep` page on the same server.
   This is the representative's page.

4. The representative will immediately be prompted for a name. Submitting the form also requires
   allowing access to the camera and microphone.

5. In order to start a chat with the customer, the representative will need to click on the "Next
   Customer" button. The first customer who successfully requested the representative will be
   matched first.

   If there was more than one customer who had successfully requested a representative, that
   customer would be next after the call ends. If there was more than one representative online,
   then more than one customer can also be serviced at a single time.

6. Once either of the users chooses to "End" or if the customer leaves the page, the call will
   complete. The representative needs to once again click on the "Next Customer" button to indicate
   that he or she is ready to speak to another customer.

## Code and Conceptual Walkthrough

### Technologies

**TODO**

### Concepts

**TODO**

### Server

**TODO**

### Client

**TODO**

## Requirements

*  PHP 5.3 or greater
*  libmemcached and the php memcached extension installed.
*  Memcached

## Next steps

Here are some other customizations that you may consider adding to the app:

*  User authentication and authorization -- **TODO**

## Appendix -- Deploying to Heroku

Heroku is a PaaS (Platform as a Service) that can be used to deploy simple and small applications
for free. For that reason, you may choose to experiment with this code and deploy it using Heroku.

*  The provided `Procfile` describes a web process which can launch this application.

*  Provision the [MemcachedCloud addon](https://addons.heroku.com/memcachedcloud). It is free for
   up to 25MB of data. Its configuration will be set for you automatically upon provisioning the
   service.

*  Use Heroku config to set the following keys:

   -  `OPENTOK_KEY` -- Your OpenTok API Key
   -  `OPENTOK_SECRET` -- Your OpenTok API Secret
   -  `SLIM_MODE` -- Set this to `production` when the environment variables should be used to
      configure the application. The Slim application will only start reading its Heroku config when
      its mode is set to `'production'`

   You should avoid committing configuration and secrets to your code, and instead use Heroku's
   config functionality.


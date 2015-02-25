# OpenTok Service Kit 

An OpenTok Starter Kit for creating a customer service application.

[![Deploy](https://www.herokucdn.com/deploy/button.png)](https://heroku.com/deploy)

## Installation

1. Clone the repository.

2. Copy the `config/development/opentok.php.sample` file to `config/development/opentok.php` and
   replace the `key` and `secret` settings with your OpenTok API key and secret, from the [TokBox
   Dashboard](https://dashboard.tokbox.com).

3. Copy the `config/development/redis.php.sample` file to `config/development/redis.php`
   and replace the `host` and `port` settings to a [Redis](http://redis.io/) instance. If the 
   instance requires authentication, you should also set the `password` setting.

4. Use [Composer](https://getcomposer.org/) (included) to install dependencies:
   `./composer.phar install`

5. Set the document root for your web server (such as Apache, nginx, etc.) to the `web` directory
   of this project. In the case of Apache, the provided `web\.htaccess` file handles URL rewriting.
   See the [Slim Route URL Rewriting Guide](http://docs.slimframework.com/#Route-URL-Rewriting)
   for more details.

## Usage

1. Visit the URL mapped to the application by your web server. This is the URL for the customer's
   page.

2. Click the "Talk to a Representative" button. Enter information in the form and then click the
   "Request a Representative" button.

3. When prompted, allow the page access access to your camera and microphone.

4. Have another user visit the `/rep` page on the same server. This is the representative's page.
   For testing, you can simply open this page in another browser window or tab (but be sure to
   mute the computer's speaker, to prevent audio feedback).

5. Allow access to the camera and microphone in the `/rep` page. Then enter the representative's
   name and click the "Login" button.

5. Click the "Get Customer" button in the `/rep` page.

   If a customer is in line, the representative is connected to the customer. If there are
   no customers in line, the representative is connected when a new customer requests
   a representative.

   If there is more than one customer online, each can be connected to different
   representatives. If there are more customers requesting a representative than there are available
   representatives, customers are added in a line and are connected to representatives as other
   calls end (or as new representatives join).

6. Once either of the users chooses to "End" the call or if the customer leaves the page, the call
   completes. The representative can click the "Next Customer" button again to indicate
   that he or she is ready to speak to another customer.

## Code and Conceptual Walkthrough

### Technologies

This application uses some of the same frameworks and libraries as the
[HelloWorld](https://github.com/opentok/OpenTok-PHP-SDK/tree/master/sample/HelloWorld) sample. If
you have not already gotten familiar with the code in that project, consider doing so before
continuing. Slim is a minimalistic PHP framework, so its patterns can be applied to other
frameworks and even other languages.

The client side of the application makes use of a few third-party libraries. The app uses
[Bootstrap](http://getbootstrap.com/) to help style the UI and for reusable components such
as buttons and modals. The [jQuery](http://jquery.com/) is used for common AJAX and DOM manipulation
utilities. The [EventEmitter2](https://github.com/asyncly/EventEmitter2) library is used to add an
evented API to objects. The [setImmediate](https://github.com/YuzuJS/setImmediate) library is used
to provide a cross-browser implementation to schedule tasks.

The server side code also uses [Redis](http://redis.io/) for persistence.

### Concepts

There are two types of users in the Service Kit application: customers and representatives.
Customers are users who initiate a request for communications. Representatives are users
fulfill that request by responding to the request. There can be many customers and many
representatives using the application at a given time. By default, the app matches each
customer with a representative as soon as one is available.

Note that while this type of interaction is well suited for customer service-style applications, 
it can also be used for a number of similar applications, such as expert consultation, matchmaking,
etc. Since the application is open source and customizable in every aspect, the Service Kit
is helpful for nearly any situation where there are different types of users who may need to
communicate with another type, using queues and any matching logic/algorithm.

When a customer requests service, a **help session** is created. The help session contains all
relevant information in order to describe the request (in this application, the customer's name and
some problem text), and an OpenTok session ID.

Once the help session is created and the customer has published his stream into the OpenTok
session, the customer adds the help session to the **help queue**. The help queue is a queue of
help sessions that require a representative to answer. When a representative is available, his or
her page requests to pop a help session off of the help queue (and continue polling for a new
one if there is none available), connect to the OpenTok session assigned to it, and communicate
with the customer. If the customer decides to cancel the help session, or closes his or her page,
the help session is removed from the help queue.

### Server

The server responds in a RESTful manner to a few different paths. Each path, described below, has
its own handler in the Slim application:

*  `POST /help/session` -- The server receives data required to create a help session. After the
   server validates the data, an OpenTok session ID is created, and the whole representation is
   stored in Redis. It is stored with a key of the format `helpsession:<sessionId>`, where
   `<sessionId>` is an OpenTok session ID. Any unique identifier could have been chosen, but the
   OpenTok session ID is a convenient choice. The response back to the client contains the session
   ID and the other data required for the client to connect to it (such as the OpenTok API key and
   a token for the session).

*  `POST /help/queue` -- The server enqueues a help session into the help queue. The client sends
   the OpenTok session ID (because it was chosen as a unique identifier for the help session, as
   explained above). That help session is validated by making sure the key exists in Redis. Then
   the reference to the help session is pushed onto the help queue, a Redis-backed queue data
   structure. The server responds to the client with a `queueId` on success.

*  `DELETE /help/queue` -- When a representative is available to help a customer, the `\rep` page
   sends this request to dequeue a help session from the help queue. In this implementation,
   the queue matches a representative to the first customer who entered the queue (FIFO), but any
   matching logic or algorithm could be employed here. First the queue is checked to see if it is
   empty or not, and if it is then a 204 No Content response is sent. If not, then the reference to
   the help session (`queueId`) is popped from the queue. That reference is used to lookup the
   details of the help session from Redis. Those details include the customer name, the problem
   details OpenTok session ID where the customer is waiting, as well as the API key and token needed
   for the representative to connect. Just before responding to the representative with these
   details, the help session details are removed from the Redis store.

*  `DELETE /help/queue/:queueId` -- When a customer decides to cancel or leave the help session, the
   server receives this request. The server simply removes the passed `queueId` from the help queue,
   and removes the help session from Redis.

*  `GET /` -- The customer HTML landing page.

*  `GET /rep` -- The representative HTML landing page.

### Client

#### Customer (web/js/customer.js)

##### serviceRequest

The `serviceRequest` object is a singleton object which behaves as a controller for the Service
Request modal and the form contained within it. Its responsibility is to gather any input
from the user which is required to create a help session. When the user clicks the "Talk to
a Representative" button, the page calls the `serviceRequest.init()` method. In
your application, you may choose to invoke it in some other manner. Once its `init()` method is
called, the `serviceRequest` object manages displaying of the modal. The selector for the modal's
DOM element is passed in as an argument to the `init()` method. This DOM element must be included on
the same page, but can be customized. The modal is defined in the `templates/customer.php` page
with the ID `service-request-modal`. Upon submission of its form, the `serviceRequest` object
performs validation, and sends the server a request to create a help session. Once the help session
is created, the `serviceRequest` object invokes a callback with the data it received from the server
(the OpenTok session ID, API key, and token).


##### ServicePanel

The `ServicePanel` object is a controller for the actual communications with the representative. It
is coupled with a floating panel view, found in `templates/customer.php` in the the element with
the ID `service-panel`. It connects to the help session and presents the proper controls and user
interface throughout the duration of the help session. Since a user can request a help session many
times while on the page, the Service Panel is instantiated and not a singleton. It has an evented
API for for `open` and `close` events. In this implementation, the page uses those events to
enable and disable the "Talk to a Representative" button, but other actions may be taken. The
`ServicePanel` object also has a `close()` method, which dismisses the user interface and properly
ends the session. The ServicePanel includes UI that tells the user to allow the page to access the
microphone and camera, it informs the user that a representative is being requested by showing a
"Waiting" message, it allows the user to Cancel or End the help session, and it handles errors gracefully.

#### Representative (web/js/customer.js)

##### `serviceProviderLogin`

The `serviceProviderLogin` object is a singleton which behaves as a controller for Representative
Login modal and the form contained within it. Its responsibility is to gather required input from
the representative before starting to communicate with customers. This ensures that the representative has given the browser permission to use the camera and mic. Also, the representative
is required to provide a name. Notably, this implementation does not send any request to
the server to record any information (such as the name). That is because authentication and
authorization is outside the scope of Service Kit. However, these are recommended in production.
The modal is defined in the `templates/representative.php` file in the element with the ID `service-provider-login-modal`. The `serviceProviderLogin` object exposes an `init()` method,
which expects the selector for the modal element, a publisher config object, and a completion handler as arguments. The publisher config object is used to place the representative's
Publisher UI onto the page and specify its properties. The reason it must be specified early is
because the same Publisher instance is reused from help session to help session and even before any
session is connected at all. This allows the representative to not need to repetitively allow the
browser the camera and mic permissions over and over again.

##### serviceProvider

The `serviceProvider` is a singleton object which behaves as a controller for the set of help
sessions that the representative will be communicating within. Its responsibility is to retrieve
help sessions from the server's help queue, connect to the corresponding OpenTok session, and
display its customer information (name and problem text in this implementation). The HTML 
defining the view is included in `templates/representative.php` in the element with the ID
`service-provider`. Within that view are controls to get another customer (when not connected to a
help session) and to end a call with a customer (while connected to a help session). The "Get
Customer" button triggers a request for a help session to be dequeued from the help queue on the
server. If none is available, the Service Provider objects continues to poll the server for one
until one is available. If after connecting to a help session, the customer's connection is not also
available after 5 seconds (`customerWaitExpirationInterval`), the customer is considered to have
left the session, and the session is treated as if it ended.

#### Utilities (web/js/utils.js)

This file is included in both the customer and representative pages to expose functions that are
useful to both. Currently it contains two functions: `presentAlert()` and `validateForm()`. The
first is used to display an error or informational message in a user friendly manner. The second is
used by both the `serviceRequest` and `serviceProviderLogin` objects to validate the inputs against
a set of rules and then display any errors if needed.

## Requirements

*  PHP 5.3 or greater
*  Redis 2.0 or greater

## Next steps

Here are some other customizations that you may consider adding to the app:

*  Representative authentication and authorization -- In this application customers can be
   completely anonymous. But representatives are likely to be restricted to individuals
   that represent your company or service. Also, identifying a representative with a
   login is important for tracking and analyzing service metrics. See the `serviceProviderLogin`
   object, described above, for a placeholder in the client side for login. Also consider
   adding an authentication and authorization implementation in the server, using a session cookie
   or similar mechanism.

*  Permanent store for help sessions -- In this implementation help sessions are stored to Redis,
   and once they've been dequeued they are removed. For your application, you may want to store all
   the help sessions in a permanent store, such as a database. You also may want to store more data
   than just the customer name and problem text. For example, you may want to store the
   representative who spoke to them, the product they were interested, etc. Redis is not typically
   used for permanent storage, so consider using a persistent store which is, such as MySQL,
   MySQL, MongoDB, PostgreSQL, Microsoft SQL Server, etc.

*  Service metrics -- It may be important to your application to track other metrics related to the
   individual help sessions, such as call time, feedback/ratings, average requests for hours of the
   day, etc. Consider storing additional data as it is generated and presenting a page where admins
   can view this type of data.

## Appendix -- Deploying to Heroku

Heroku is a PaaS (Platform as a Service) that can be used to deploy simple and small applications
for free. For that reason, you may choose to experiment with this code and deploy it using Heroku.

Use the button at the top of the README to deploy to Heroku in one click!

If you'd like to deploy manually, here is some additional information:

*  The provided `Procfile` describes a web process which can launch this application.

*  Provision the [RedisToGo addon](https://addons.heroku.com/redistogo). It is free for
   up to 5MB of data. Its configuration will be set for you automatically upon provisioning the
   service.

*  Use Heroku config to set the following keys:

   -  `OPENTOK_KEY` -- Your OpenTok API Key
   -  `OPENTOK_SECRET` -- Your OpenTok API Secret
   -  `SLIM_MODE` -- Set this to `production` when the environment variables should be used to
      configure the application. The Slim application will only start reading its Heroku config when
      its mode is set to `'production'`

   You should avoid committing configuration and secrets to your code, and instead use Heroku's
   config functionality.

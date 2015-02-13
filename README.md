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

4. Use [Composer](https://getcomposer.org/)(included) to install dependencies:
   `./composer.phar install`

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

This application uses some of the same frameworks and libraries as the
[HelloWorld](https://github.com/opentok/OpenTok-PHP-SDK/tree/master/sample/HelloWorld) sample. If
you have not already gotten familiar with the code in that project, consider doing so before
continuing. Slim is a minimalistic PHP framework, so its patterns can be applied to other
frameworks and even other languages.

The client side of the application makes use of a few third party libraries.
[Bootstrap](http://getbootstrap.com/) is used to help style the UI and for reusable components such
as buttons and modals. [jQuery](http://jquery.com/) is used for common AJAX and DOM manipulation
utilities. The [EventEmitter2](https://github.com/asyncly/EventEmitter2) library is used to add an
evented API to objects. The [setImmediate](https://github.com/YuzuJS/setImmediate) library is used
to provide a cross-browser implementation to schedule tasks.

The server side also uses [Redis](http://redis.io/) for persistence.

### Concepts

There are two types of users in the Service Kit application: customers and representatives.
Customers are users who initiate a request for communcations, while representatives are users
fulfill that request by responding to the request. There can be many customers and many
representatives on the application at a given time. By default, each customer will be matched with
a representative as soon as one is available.

Note that while this type of interaction is very well suited for Customer Service style
applications, it can also be used for a number of similar usages such as expert consultation,
matchmaking, etc. Since the application is open source and customizable in every aspect, the Service
Kit is helpful for nearly any situation where there are different types of users who may need to
communicate with another type, using queues and any matching logic/algorithm.

When a customer requests service, a **help session** is created. The help session contains all
relevent information in order to describe the request (in this application, the customer's name and
some problem text), and an OpenTok session ID.

Once the help session is created and the customer has published his stream into the OpenTok
session, the customer adds the help session to the **help queue**. The help queue is a queue of
help sessions that require a representative to answer. When a representative is available, his or
her page will request to pop a help session off of the help queue (and continue polling for a new
one if there is none available), connect to the OpenTok session assigned to it, and communicate
with the customer. If for some reason, the customer decides to cancel their help session, or closes
his or her page, the help session will be removed from the help queue.

### Server

The server responds in a RESTful manner to a few different paths. Each path is given its own handler
in the Slim application and is individually described below:


*  `POST /help/session` -- The server receives data required to create a help session. After the
   server validates the data, an OpenTok session ID is created, and the whole representation is
   stored in Redis. It is stored with a key of the format `helpsession:<sessionId>`, where
   `<sessionId>` is an OpenTok session ID. Any unique identifier could have been chosen, but the
   OpenTok session ID is a convenient choice. The response back to the client contains that created
   session ID, and the other data required for the client to connect to it (API key and token).

*  `POST /help/queue` -- The server enqueues a help session into the help queue. The client sends
   the OpenTok session ID (because it was chosen as a unique identifier for the help session as
   explained above). That help session is validated by making sure the key exists in Redis. Then,
   the reference to the help session is pushed onto the help queue, a Redis backed queue data
   structure. The server responds to the client with a `queueId` on success.

*  `DELETE /help/queue` -- When the representative is available to help a customer, the server
   receives this request for dequeuing a help session from the help queue. In this implementation,
   the queue matches a representative to the first customer who entered the queue (FIFO), but any
   matching logic or algorithm could be emplyed here. First the queue is checked to see if its empty
   or not, and if it is then a 204 No Content response is sent. If not, then the reference to the
   help session (`queueId`) is popped from the queue. That reference is used to lookup the details
   of the help session from Redis. Those details include the customer name, the problem text, the
   OpenTok session ID where the customer is waiting, as well as the API Key and token needed for the
   representative to connect. Just before responding to the representative with these details, the
   help session details are removed from the redis store.

*  `DELETE /help/queue/:queueId` -- When a customer decides to cancel or leave the help session, the
   server receives this request. The server simply removes the passed `queueId` from the help queue,
   and removes the help session from Redis.

*  `GET /` -- The customer HTML landing page

*  `GET /rep` -- The representative HTML landing page

### Client

#### Customer (web/js/customer.js)

##### Service Request

The Service Request object is a singleton object which behaves as a controller for the Service
Request modal and the form contained within it. Its responsibility is to gather any input
from the user which is required to create a help session. In order to it to work, the page invokes
its `init()` method, in this case using a click on a button which reads "Talk to a Representative". In
your application, you may choose to invoke it in some other manner. Once its `init()` method is
invoked, the Service Request object manages displaying of the Modal, whose selector is passed in as
an argument. Its markup must be included on the same page, but can be customized. You will find the
markup in the `templates/customer.php` view, in the element with the ID `service-request-modal`. The
Service Request object will also performs validation, and upon submission of its form, will send the
server a request to create a help session. Once the help session is created, the Service Request
object will invoke a callback with the data it recieved from the server (the OpenTok session ID, API
Key, and token).


##### Service Panel

The Service Panel object is a controller for the actual communications with the representative. It
is coupled with a floating panel view whose markup is also found in `templates/customer.php` in the
element with the ID `service-panel`. Its responsibility is to connect to the help session and
present the proper contols and user interface to the user throughout the duration of the help
session. Since it is reasonable for a user to request a help session many times while on the page,
the Service Panel is instantiated and not a singleton. It has an evented API for the rest of the
page to bind to, in which it fires both `open` and `close` events. In this implementation, the page
uses those events to enable/disable the "Talk to a Representative" button, but other actions may be
taken. The Service Panel also has a `close()` method which the page can call to immediately dismiss
the interface and properly end the session. Some of the features of the panel are that it will help
inform the user that they must permit their borwser to use the mic and camera, it informs the user
that a representative is being requested by showing a "Waiting" message, it allows the user to
Cancel or End the help session, and it handles errors gracefully.

#### Representative (web/js/customer.js)

##### Service Provider Login

The Service Provider Login is a singleton object which behaves as a controller for Representative
Login modal and the form contained within it. Its responsibility is to handle gather any input from
the representative which is needed before starting to communicate with customers. In this
implementation, we ensure that the representative has given the browser permission to use the camera
and mic. Also, a name field is required. Notably, this implementation does not send any request to
the server to record any information (such as the name). That is because authentication and
authroization is outside the scope of Service Kit, but its recommended for anyone who implements
this in production to properly address it. The markup for the modal can be found in 
`templates/representative.php` in the element with ID `service-provider-login-modal`. The Service
Provider Login object exposes an `init()` method which expects that selector, a publisher config,
and a completion handler as arguments. The publisher config is used to place the representatives
Publisher UI onto the page, and specify its properties. The reason it must be specified early is
because the same Publisher instance is reused from help session to help session and even before any
session is connected at all. This allows the representative to not need to repetitively allow the
browser the camera and mic permissions over and over again.

##### Service Provider

The Service Provider is a singleton object which behaves as a controller for the set of help
sessions that the representative will be communicating within. Its responsibility is to retrieve
help sessions from the server's help queue, connect to that help session OpenTok session, and
display its customer information (name and problem text in this implementation). The markup required
for its view is incuded in `templates/representative.php` in the element with the ID
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
used by both the Service Request and Service Provider Login objects to validate the inputs against
a set of rules, and then display any errors if needed.

## Requirements

*  PHP 5.3 or greater
*  Redis 2.0 or greater

## Next steps

Here are some other customizations that you may consider adding to the app:

*  Representative authentication and authorization -- In this application it is reasonable for
   customers to be completely anonymous. But representatives are likely individuals who should be
   restricted to those that represent your company or service. Also, identifying who a representtive
   is with login is important for tracking and analyzing service metrics. See the Service Provider
   Login object, described above, for a placeholder in the client side for login. Also consider
   adding an authentication and authorization implementation in the server, using a session cookie
   or similar mechanism.

*  Permanent store for help sessions -- In this implementation help sessions are stored to Redis,
   and once they've been dequeued they are removed. For your application, you may want to store all
   the help sessions (along with more data than just the customer name and problem text, perhaps the
   representative who spoke to them, the product they were interested, etc) in a permanent store
   such as a database. Redis is not typically used for permanent storage, so consider using
   a persistent store which is (e.g. MySQL, MongoDB, PostgreSQL, Microsoft SQL Server, etc).

*  Service metrics -- It may be important to your application to track other metrics related to the
   individual help sessions such as call time, feedback/ratings, average requests for hours of the
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


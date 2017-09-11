# Upgrading to 4.0

## Zend\Mvc\ResponseSender

Response sender was designed for zend-stdlib responses. Response senders
and `Zend\Mvc\SendResponseListener` are removed from zend-mvc 4.0 as part
of PSR-7 migration.

The v4 release now utilizes
[response emitters of zend-httphandlerrunner](https://docs.zendframework.com/zend-httphandlerrunner/emitters/)

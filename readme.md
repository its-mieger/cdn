# CDN

Content-Delivery-Network module to publish files to an external CDN and access URLs easily.


## Include CDN to application

Simply include the generated cdn.php

	require_once('vendor/cdn.php');

	
## Sync files to CDN

Call cdn-push script:

	cdn-push [path to cdn.json]
	
	
## cdn.json

Example:
	
	{
    	"root-dir": "webroot",
    	"default-config": {
    		"adapter": "S3",
    		"aws": {
    			"region": "eu-west-1",
    			"credentials": {
    				"key": "",
    				"secret": ""
    			}
    		},
    		"bucket": "test-bucket",
    		"url": "test.test.de"
    	},
    	"paths": {
    		"img": true,
    		"css": {
    			"append-hash": true
    		}
    	}
    }
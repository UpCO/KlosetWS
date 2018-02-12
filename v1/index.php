<?php
require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '../vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app = new \Slim\App;


// User uid from db - Global Variable
$user_uid = NULL;

/**
 *  Verifying required params posted or not
 *  @param String $required_fields Required fields to be verified
 *  @param RequestInterface $request RequestInterface
 *  @param ResponseInterface $response ResponseInterface
 *  @return ResponseInterface Returns a ResponseInterface
 */
function verifyRequiredParams($required_fields, $request, $response) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $request->getParsedBody();

    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $res = array();
        $res["error"] = true;
        $res["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        $res["parameters"] = array();
        return echoResponse(400, $res, $response);
    }

    return NULL;
}

/**
 *  Validating email address
 *  @param String $email Email to validate
 *  @param ResponseInterface $response ResponseInterface
 *  @return ResponseInterface Returns a ResponseInterface
 */
function validateEmail($email, $response) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $res["error"] = true;
        $res["message"] = 'Email address is not valid';
        $res["parameters"] = array();
        return echoResponse(400, $res, $response);
    }

    return NULL;
}

/**
 *  Adding Middle Layer to authenticate every request
 *  Checking if the request has valid uid in the 'Authorization' header
 *  @param ResponseInterface $response A ResponseInterface
 *  @return ResponseInterface Returns a ResponseInterface
 */
function authenticate($response) {
    // Getting request headers
    $headers = apache_request_headers();
    $res = array();

    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
        $db = new DbHandler();

        // get the api key
        $api_key = $headers['Authorization'];

        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $res["error"] = true;
            $res["message"] = "Access Denied. Invalid API key";
            $res["parameters"] = array();
            return echoResponse(401, $res, $response);
        } else {
            global $user_uid;
            // get user uid by api key
            $uid = $db->getUidByApiKey($api_key);
            if ($uid != NULL)
            	$user_uid = $uid["uid"];
        }
    } else {
        // api key is missing in header
        $res["error"] = true;
        $res["message"] = "API key is missing";
        $res["parameters"] = array();
        return echoResponse(400, $res, $response);
    }

    return NULL;
}


/**
 *  Echoing json response to client
 *  @param Int $status_code Http response code
 *  @param Int $res Json response
 *  @param ResponseInterface $response ResponseInterface
 *	@return ResponseInterface Returns a ResponseInterface
 */
function echoResponse($status_code, $res, $response) {
    echo json_encode($res);

    // Setting http response code and content type to json
    return $response->withStatus($status_code)->withHeader('Content-type', 'application/json');
}

/**
 *  User Registration
 *  url - /register
 *  method - POST
 *  params - name, email, password
 */
$app->post('/register', function (Request $request, Response $response, $args) {
    // check for required params
    $ret = verifyRequiredParams(array('name', 'email', 'password'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $name = $request->getParam('name');
    $email = $request->getParam('email');
    $password = $request->getParam('password');

    // validating email address
    $ret = validateEmail($email, $response);
    if ($ret != NULL) {
        return $ret;
    }

	$res = array();
    $db = new DbHandler();
    
    // creating new user
    $ret = $db->createUser($name, $email, $password);

    if ($ret == USER_CREATED_SUCCESSFULLY) {
        $res["error"] = false;
        $res["message"] = "You are successfully registered";
        $res["parameters"] = array();
        return echoResponse(201, $res, $response);
    } else if ($ret == USER_CREATE_FAILED) {
        $res["error"] = true;
        $res["message"] = "Oops! An error occurred while registering";
        $res["parameters"] = array();
        return echoResponse(200, $res, $response);
    } else if ($ret == USER_ALREADY_EXISTED) {
        $res["error"] = true;
        $res["message"] = "Sorry, this email already existed";
        $res["parameters"] = array();
        return echoResponse(200, $res, $response);
    }
});

/**
 *  User Login
 *  url - /login
 *  method - POST
 *  params - email, password
 */
$app->post('/login', function (Request $request, Response $response, $args) {
    // check for required params
    $ret = verifyRequiredParams(array('email', 'password'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $email = $request->getParam('email');
    $password = $request->getParam('password');

	$res = array();
    $db = new DbHandler();

    // Check for correct email and password
    if ($db->checkLogin($email, $password)) {
        // get the user by email
        $user = $db->getUserByEmail($email);

        if ($user != NULL) {
            $res["error"] = false;
            $res["message"] = "You are successfully logged";
            $res["parameters"] = array();
            $res["id"] = $user["id"];
            $res["uid"] = $user["uid"];
            $res["name"] = $user["name"];
            $res["email"] = $user["email"];
            $res["api_key"] = $user["api_key"];
            $res["birthday"] = $user["birthday"];
            $res["location"] = $user["location"];
            $res["about"] = $user["about"];
            $res["updated_at"] = $user["updated_at"];
            $res["created_at"] = $user["created_at"];
        } else {
            // unknown error occurred
            $res["error"] = true;
            $res["message"] = "An error occurred. Please try again";
            $res["parameters"] = array();
        }
    } else {
        // user credentials are wrong
        $res["error"] = true;
        $res["message"] = "Login failed. Incorrect credentials";
        $res["parameters"] = array();
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Creating new post in db
 *  url - /posts
 *  method - POST
 *  params - content, privacy, num_likes, num_comments, num_shares
 */
$app->post('/posts', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('content', 'privacy', 'num_likes', 'num_comments', 'num_shares'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $content = $request->getParam('content');
    $privacy = $request->getParam('privacy');
    $num_likes = $request->getParam('num_likes');
    $num_comments = $request->getParam('num_comments');
    $num_shares = $request->getParam('num_shares');

    global $user_uid;
    $res = array();
    $db = new DbHandler();

    // creating new post
    $post_uid = $db->createPost($user_uid, $content, $privacy, $num_likes, $num_comments, $num_shares);

    if ($post_uid != NULL) {
        $res["error"] = false;
        $res["message"] = "Post created successfully";
        $res["parameters"] = array();

        $tmp["uid"] = $post_uid;
        array_push($res["parameters"], $tmp);
    } else {
        $res["error"] = true;
        $res["message"] = "Failed to create post. Please try again.";
        $res["parameters"] = array();
    }

    return echoResponse(201, $res, $response);
});

/**
 *  Listing all posts of particular user
 *  url - /posts
 *  method - GET
 */
$app->get('/posts', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    global $user_uid;
    $res = array();
    $db = new DbHandler();

    // fetching all user posts
    $ret = $db->getAllUserPosts($user_uid);

    $res["error"] = false;
    $res["message"] = "Posts found successfully";
    $res["parameters"] = array();

    // looping through result and preparing posts array
    while ($post = $ret->fetch_assoc()) {
        $tmp = array();
        $tmp["id"] = $post["id"];   
        $tmp["uid"] = $post["uid"]; 
        $tmp["content"] = $post["content"]; 
        $tmp["privacy"] = $post["privacy"]; 
        $tmp["num_likes"] = $post["num_likes"]; 
        $tmp["num_comments"] = $post["num_comments"];   
        $tmp["num_shares"] = $post["num_shares"];   
        $tmp["updated_at"] = $post["updated_at"];   
        $tmp["created_at"] = $post["created_at"];
        array_push($res["parameters"], $tmp);
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Listing single post of particular user
 *  url - /posts/{uid}
 *  method - GET
 *  Will return 404 if the post doesn't belongs to user
 */
$app->get('/posts/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    global $user_uid;
    $post_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // fetch post
    $ret = $db->getPost($post_uid, $user_uid);

    if ($ret != NULL) {
        $res["error"] = false;
        $res["message"] = "Post found successfully";
        $res["parameters"] = array();

        $tmp["id"] = $ret["id"];    
        $tmp["uid"] = $ret["uid"];  
        $tmp["content"] = $ret["content"];  
        $tmp["privacy"] = $ret["privacy"];   
        $tmp["num_likes"] = $ret["num_likes"];  
        $tmp["num_comments"] = $ret["num_comments"];    
        $tmp["num_shares"] = $ret["num_shares"];    
        $tmp["updated_at"] = $ret["updated_at"];    
        $tmp["created_at"] = $ret["created_at"];
        array_push($res["parameters"], $tmp);

        return echoResponse(200, $res, $response);
    } else {
        $res["error"] = true;
        $res["message"] = "The requested resource doesn't exists";
        $res["parameters"] = array();
        return echoResponse(404, $res, $response);
    }
});

/**
 *  Updating existing post
 *  url - /posts/{uid}
 *  method - PUT
 *  params - content, privacy, num_likes, num_comments, num_shares
 */
$app->put('/posts/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('content', 'privacy', 'num_likes', 'num_comments', 'num_shares'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $content = $request->getParam('content');
    $privacy = $request->getParam('privacy');
    $num_likes = $request->getParam('num_likes');
    $num_comments = $request->getParam('num_comments');
    $num_shares = $request->getParam('num_shares');

    global $user_uid;
    $post_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // updating post
    $ret = $db->updatePost($post_uid, $user_uid, $content, $privacy, $num_likes, $num_comments, $num_shares);

    if ($ret) {
        // post updated successfully
        $res["error"] = false;
        $res["message"] = "Post updated successfully";
        $res["parameters"] = array();
    } else {
        // post failed to update
        $res["error"] = true;
        $res["message"] = "Failed to update post. Please try again.";
        $res["parameters"] = array();
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Deleting post. Users can delete only their posts.
 *  url - /posts/{uid}
 *  method - DELETE
 */
$app->delete('/posts/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    global $user_uid;
    $post_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // deleting post
    $ret = $db->deletePost($post_uid, $user_uid);

    if ($ret) {
        // post deleted successfully
        $res["error"] = false;
        $res["message"] = "Post deleted successfully";
        $res["parameters"] = array();
    } else {
        // post failed to delete
        $res["error"] = true;
        $res["message"] = "Failed to delete post. Please try again.";
        $res["parameters"] = array();
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Creating new look in db
 *  url - /looks
 *  method - POST
 *  params - title, privacy, num_items, num_likes, num_comments, num_shares
 */
$app->post('/looks', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('title', 'privacy', 'num_items', 'num_likes', 'num_comments', 'num_shares'), $request, $response);
    if ($ret != NULL) {
    	return $ret;
    }

    // reading post params
    $title = $request->getParam('title');
    $privacy = $request->getParam('privacy');
    $num_items = $request->getParam('num_items');
    $num_likes = $request->getParam('num_likes');
    $num_comments = $request->getParam('num_comments');
    $num_shares = $request->getParam('num_shares');

    global $user_uid;
    $res = array();
    $db = new DbHandler();

    // creating new look
    $look_uid = $db->createLook($user_uid, $title, $privacy, $num_items, $num_likes, $num_comments, $num_shares);

    if ($look_uid != NULL) {
    	$res["error"] = false;
    	$res["message"] = "Look created successfully";
        $res["parameters"] = array();

    	$tmp["uid"] = $look_uid;
        array_push($res["parameters"], $tmp);
    } else {
    	$res["error"] = true;
    	$res["message"] = "Failed to create look. Please try again.";
        $res["parameters"] = array();
    }

    return echoResponse(201, $res, $response);
});

/**
 *  Listing all looks of particular user
 *  url - /looks
 *  method - GET
 */
$app->get('/looks', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    global $user_uid;
    $res = array();
    $db = new DbHandler();

    // fetching all user looks
    $ret = $db->getAllUserLooks($user_uid);

    $res["error"] = false;
    $res["message"] = "Looks found successfully";
    $res["parameters"] = array();

    // looping through result and preparing looks array
    while ($look = $ret->fetch_assoc()) {
    	$tmp = array();
    	$tmp["id"] = $look["id"];	
    	$tmp["uid"] = $look["uid"];	
    	$tmp["title"] = $look["title"];	
    	$tmp["privacy"] = $look["privacy"];	
    	$tmp["num_items"] = $look["num_items"];	
    	$tmp["num_likes"] = $look["num_likes"];	
    	$tmp["num_comments"] = $look["num_comments"];	
    	$tmp["num_shares"] = $look["num_shares"];	
    	$tmp["updated_at"] = $look["updated_at"];	
    	$tmp["created_at"] = $look["created_at"];
    	array_push($res["parameters"], $tmp);
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Listing single look of particular user
 *  url - /looks/{uid}
 *  method - GET
 *	Will return 404 if the look doesn't belongs to user
 */
$app->get('/looks/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    global $user_uid;
    $look_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // fetch look
    $ret = $db->getLook($look_uid, $user_uid);

    if ($ret != NULL) {
    	$res["error"] = false;
        $res["message"] = "Look found successfully";
        $res["parameters"] = array();

        $tmp = array();
    	$tmp["id"] = $ret["id"];	
    	$tmp["uid"] = $ret["uid"];	
    	$tmp["title"] = $ret["title"];	
    	$tmp["privacy"] = $ret["privacy"];	
    	$tmp["num_items"] = $ret["num_items"];	
    	$tmp["num_likes"] = $ret["num_likes"];	
    	$tmp["num_comments"] = $ret["num_comments"];	
    	$tmp["num_shares"] = $ret["num_shares"];	
    	$tmp["updated_at"] = $ret["updated_at"];	
    	$tmp["created_at"] = $ret["created_at"];
        array_push($res["parameters"], $tmp);

    	return echoResponse(200, $res, $response);
    } else {
    	$res["error"] = true;
    	$res["message"] = "The requested resource doesn't exists";
        $res["parameters"] = array();
    	return echoResponse(404, $res, $response);
    }
});

/**
 *  Updating existing look
 *  url - /looks/{uid}
 *  method - PUT
 *	params - title, privacy, num_items, num_likes, num_comments, num_shares
 */
$app->put('/looks/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('title', 'privacy', 'num_items', 'num_likes', 'num_comments', 'num_shares'), $request, $response);
    if ($ret != NULL) {
    	return $ret;
    }

    // reading post params
    $title = $request->getParam('title');
    $privacy = $request->getParam('privacy');
    $num_items = $request->getParam('num_items');
    $num_likes = $request->getParam('num_likes');
    $num_comments = $request->getParam('num_comments');
    $num_shares = $request->getParam('num_shares');

    global $user_uid;
    $look_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // updating look
    $ret = $db->updateLook($look_uid, $user_uid, $title, $privacy, $num_items, $num_likes, $num_comments, $num_shares);

    if ($ret) {
    	// look updated successfully
    	$res["error"] = false;
    	$res["message"] = "Look updated successfully";
        $res["parameters"] = array();
    } else {
    	// look failed to update
    	$res["error"] = true;
    	$res["message"] = "Failed to update look. Please try again.";
        $res["parameters"] = array();
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Deleting look. Users can delete only their looks.
 *  url - /looks/{uid}
 *  method - DELETE
 */
$app->delete('/looks/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    global $user_uid;
    $look_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // deleting look
    $ret = $db->deleteLook($look_uid, $user_uid);

    if ($ret) {
    	// look deleted successfully
    	$res["error"] = false;
    	$res["message"] = "Look deleted successfully";
        $res["parameters"] = array();
    } else {
    	// look failed to delete
    	$res["error"] = true;
    	$res["message"] = "Failed to delete look. Please try again.";
        $res["parameters"] = array();
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Creating new item in db
 *  url - /items
 *  method - POST
 *  params - look_uid, title, images
 */
$app->post('/items', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('look_uid', 'title', 'images'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $look_uid = $request->getParam('look_uid');
    $title = $request->getParam('title');
    $images = $request->getParam('images');

    $res = array();
    $db = new DbHandler();

    // creating new item
    $item_uid = $db->createItem($look_uid, $title, $images);

    if ($item_uid != NULL) {
        $res["error"] = false;
        $res["message"] = "Item created successfully";
        $res["item_uid"] = $item_uid;
    } else {
        $res["error"] = true;
        $res["message"] = "Failed to create item. Please try again.";
    }

    return echoResponse(201, $res, $response);
});

/**
 *  Listing all items of particular look
 *  url - /items
 *  method - GET
 *  params - look_uid
 */
$app->get('/items', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('look_uid'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $look_uid = $request->getParam('look_uid');

    $res = array();
    $db = new DbHandler();

    // fetching all look items
    $ret = $db->getAllLookItems($look_uid);

    $res["error"] = false;
    $res["items"] = array();

    // looping through result and preparing items array
    while ($item = $ret->fetch_assoc()) {
        $tmp = array();
        $tmp["id"] = $item["id"];   
        $tmp["uid"] = $item["uid"]; 
        $tmp["title"] = $item["title"]; 
        $tmp["images"] = $item["images"];  
        $tmp["updated_at"] = $item["updated_at"];   
        $tmp["created_at"] = $item["created_at"];
        array_push($res["items"], $tmp);
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Listing single item of particular look
 *  url - /items/{uid}
 *  method - GET
 *  params - look_uid
 *  Will return 404 if the item doesn't belongs to look
 */
$app->get('/items/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('look_uid'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $look_uid = $request->getParam('look_uid');

    $item_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // fetch item
    $ret = $db->getItem($item_uid, $look_uid);

    if ($ret != NULL) {
        $res["error"] = false;
        $res["id"] = $ret["id"];    
        $res["uid"] = $ret["uid"];  
        $res["title"] = $ret["title"];  
        $res["images"] = $ret["images"];  
        $res["updated_at"] = $ret["updated_at"];    
        $res["created_at"] = $ret["created_at"];
        return echoResponse(200, $res, $response);
    } else {
        $res["error"] = true;
        $res["message"] = "The requested resource doesn't exists";
        return echoResponse(404, $res, $response);
    }
});

/**
 *  Updating existing item
 *  url - /items/{uid}
 *  method - PUT
 *  params - look_uid, title, images
 */
$app->put('/items/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('look_uid', 'title', 'images'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $look_uid = $request->getParam('look_uid');
    $title = $request->getParam('title');
    $images = $request->getParam('images');

    $item_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // updating item
    $ret = $db->updateItem($item_uid, $look_uid, $title, $images);

    if ($ret) {
        // item updated successfully
        $res["error"] = false;
        $res["message"] = "Item updated successfully";
    } else {
        // item failed to update
        $res["error"] = true;
        $res["message"] = "Failed to update item. Please try again.";
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Deleting item. Users can delete only their look items.
 *  url - /items/{uid}
 *  method - DELETE
 *  params - look_uid
 */
$app->delete('/items/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('look_uid'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $look_uid = $request->getParam('look_uid');

    $item_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // deleting look
    $ret = $db->deleteItem($item_uid, $look_uid);

    if ($ret) {
        // item deleted successfully
        $res["error"] = false;
        $res["message"] = "Item deleted successfully";
    } else {
        // item failed to delete
        $res["error"] = true;
        $res["message"] = "Failed to delete item. Please try again.";
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Creating new comment in db
 *  url - /comments
 *  method - POST
 *  params - entity_uid, entity_type, type, content, num_likes
 */
$app->post('/comments', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('entity_uid', 'entity_type', 'type', 'content', 'num_likes'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $entity_uid = $request->getParam('entity_uid');
    $entity_type = $request->getParam('entity_type');
    $type = $request->getParam('type');
    $content = $request->getParam('content');
    $num_likes = $request->getParam('num_likes');

    $res = array();
    $db = new DbHandler();

    // creating new comment
    $comment_uid = $db->createComment($entity_uid, $entity_type, $type, $content, $num_likes);

    if ($comment_uid != NULL) {
        $res["error"] = false;
        $res["message"] = "Comment created successfully";
        $res["comment_uid"] = $comment_uid;
    } else {
        $res["error"] = true;
        $res["message"] = "Failed to create comment. Please try again.";
    }

    return echoResponse(201, $res, $response);
});

/**
 *  Listing all comments of particular entity
 *  url - /comments
 *  method - GET
 *  params - entity_uid, entity_type
 */
$app->get('/comments', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('entity_uid', 'entity_type'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $entity_uid = $request->getParam('entity_uid');
    $entity_type = $request->getParam('entity_type');

    $res = array();
    $db = new DbHandler();

    // fetching all entity comments
    $ret = $db->getAllEntityComments($entity_uid, $entity_type);

    $res["error"] = false;
    $res["comments"] = array();

    // looping through result and preparing comments array
    while ($comment = $ret->fetch_assoc()) {
        $tmp = array();
        $tmp["id"] = $comment["id"];   
        $tmp["uid"] = $comment["uid"]; 
        $tmp["type"] = $comment["type"]; 
        $tmp["content"] = $comment["content"];  
        $tmp["num_likes"] = $comment["num_likes"];  
        $tmp["updated_at"] = $comment["updated_at"];   
        $tmp["created_at"] = $comment["created_at"];
        array_push($res["comments"], $tmp);
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Listing single comment of particular entity
 *  url - /comments/{uid}
 *  method - GET
 *  params - entity_uid, entity_type
 *  Will return 404 if the comment doesn't belongs to entity
 */
$app->get('/comments/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('entity_uid', 'entity_type'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $entity_uid = $request->getParam('entity_uid');
    $entity_type = $request->getParam('entity_type');

    $comment_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // fetch comment
    $ret = $db->getComment($comment_uid, $entity_uid, $entity_type);

    if ($ret != NULL) {
        $res["error"] = false;
        $res["id"] = $ret["id"];    
        $res["uid"] = $ret["uid"];  
        $res["type"] = $ret["type"];  
        $res["content"] = $ret["content"];  
        $res["num_likes"] = $ret["num_likes"];  
        $res["updated_at"] = $ret["updated_at"];    
        $res["created_at"] = $ret["created_at"];
        return echoResponse(200, $res, $response);
    } else {
        $res["error"] = true;
        $res["message"] = "The requested resource doesn't exists";
        return echoResponse(404, $res, $response);
    }
});

/**
 *  Updating existing comment
 *  url - /comments/{uid}
 *  method - PUT
 *  params - entity_uid, entity_type, content, num_likes
 */
$app->put('/comments/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('entity_uid', 'entity_type', 'content', 'num_likes'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $entity_uid = $request->getParam('entity_uid');
    $entity_type = $request->getParam('entity_type');
    $content = $request->getParam('content');
    $num_likes = $request->getParam('num_likes');

    $comment_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // updating comment
    $ret = $db->updateComment($comment_uid, $entity_uid, $entity_type, $content, $num_likes);

    if ($ret) {
        // comment updated successfully
        $res["error"] = false;
        $res["message"] = "Comment updated successfully";
    } else {
        // comment failed to update
        $res["error"] = true;
        $res["message"] = "Failed to update comment. Please try again.";
    }

    return echoResponse(200, $res, $response);
});

/**
 *  Deleting comment. Users can delete only their entity comments.
 *  url - /comments/{uid}
 *  method - DELETE
 *  params - entity_uid, entity_type
 */
$app->delete('/comments/{uid}', function (Request $request, Response $response, $args) {
    $ret = authenticate($response);
    if ($ret != NULL) {
        return $ret;
    }

    // check for required params
    $ret = verifyRequiredParams(array('entity_uid', 'entity_type'), $request, $response);
    if ($ret != NULL) {
        return $ret;
    }

    // reading post params
    $entity_uid = $request->getParam('entity_uid');
    $entity_type = $request->getParam('entity_type');

    $comment_uid = $args["uid"];
    $res = array();
    $db = new DbHandler();

    // deleting comment
    $ret = $db->deleteComment($comment_uid, $entity_uid, $entity_type);

    if ($ret) {
        // comment deleted successfully
        $res["error"] = false;
        $res["message"] = "Comment deleted successfully";
    } else {
        // comment failed to delete
        $res["error"] = true;
        $res["message"] = "Failed to delete comment. Please try again.";
    }

    return echoResponse(200, $res, $response);
});

$app->run();

?>
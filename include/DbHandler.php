<?php
require '../vendor/autoload.php';

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

/**
 *	Class to handle all db operations
 *	This class will have CRUD methods for database tables
 */
class DbHandler {
	private $conn;

	function __construct() {
		require_once dirname(__FILE__) . './DbConnect.php';

		// Opening db connection
		$db = new DbConnect();
		$this->conn = $db->connect();
	}

	/* --- 'users' table methods --- */

	/**
	 *	Creating new user
	 *	@param String $name User full name
	 *	@param String $email User login email id
	 *	@param String $password User login password
	 */
	public function createUser($name, $email, $password) {
		require_once 'PassHash.php';

		$response = array();

		// First check if user already existed in db
		if (!$this->isUserExists($email)) {
			// Generating password hash
			$password_hash = PassHash::hash($password);

			// Generating API key
			$api_key = $this->generateUID();
			if ($api_key == NULL) {
				return USER_CREATED_FAILED;
			}

			// Generating user uid
			$uid = $this->generateUID();
			if ($uid == NULL) {
				return USER_CREATED_FAILED;
			}

			// Insert query
			$stmt = $this->conn->prepare('INSERT INTO users(uid, name, email, password_hash, api_key, status) VALUES(?,?,?,?,?,1)');
			$stmt->bind_param('sssss', $uid, $name, $email, $password_hash, $api_key);
			$result = $stmt->execute();
			$stmt->close();

			// Check for successful insertion
			if ($result) {
				// User successfully inserted
				return USER_CREATED_SUCCESSFULLY;
			} else {
				// Failed to create user
				return USER_CREATE_FAILED;
			}
		} else {
			// User with same email already existed in the db
			return USER_ALREADY_EXISTED;
		}

		return $response;
	}

	/**
	 *	Checking user login
	 *	@param String $email User login email id
	 *	@param String $password User login password
	 *	@return Boolean User login status success/fail
	 */
	public function checkLogin($email, $password) {
		// Fetching user by email
		$stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE email = ?");
		$stmt->bind_param("s", $email);
		$stmt->execute();
		$stmt->bind_result($password_hash);
		$stmt->store_result();

		if ($stmt->num_rows > 0) {
			// Found user with the email
			// Now verify the password
			$stmt->fetch();
			$stmt->close();

			if (PassHash::check_password($password_hash, $password)) {
				// User password is correct
				return true;
			} else {
				// User password is incorrect
				return false;
			}
		} else {
			$stmt->close();

			// User not existed with the email
			return false;
		}
	}

	/**
	 *	Checking for duplicate user by email address
	 *	@param String $email Email to check in db
	 *	@return Boolean
	 */
	private function isUserExists($email) {
		$stmt = $this->conn->prepare("SELECT id from users WHERE email = ?");
		$stmt->bind_param("s", $email);
		$stmt->execute();
		$stmt->store_result();
		$num_rows = $stmt->num_rows;
		$stmt->close();
		return $num_rows > 0;
	}

	/**
	 *	Fetching user by email
	 *	@param String $email User email id
	 */
	public function getUserByEmail($email) {
		$stmt = $this->conn->prepare("SELECT id, uid, name, email, api_key, birthday, location, about, status, updated_at, created_at
			                          FROM users WHERE email = ?");
		$stmt->bind_param("s", $email);
		if ($stmt->execute()) {
			$user = $stmt->get_result()->fetch_assoc();
			$stmt->close();
			return $user;
		} else {
			return NULL;
		}

	}

	/**
	 *	Fetching user api key by user uid
	 *	@param String $user_uid UID of the user
	 */
	public function getApiKeyByUid($user_uid) {
		$stmt = $this->conn->prepare("SELECT api_key FROM users WHERE uid = ?");
        $stmt->bind_param("s", $user_uid);
        if ($stmt->execute()) {
            $api_key = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $api_key;
        } else {
            return NULL;
        }
	}

	/**
	 *	Fetching user uid by api key
	 *	@param String $api_key User API key
	 */
	public function getUidByApiKey($api_key) {
		$stmt = $this->conn->prepare("SELECT uid FROM users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute()) {
            $user_uid = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $user_uid;
        } else {
            return NULL;
        }
	}

	/**
	 *	Validating user api key
	 *	If the api key is there in db, it is a valid key
	 *	@param String $api_key User API key
	 *	@return Boolean
	 */
	public function isValidApiKey($api_key) {
		$stmt = $this->conn->prepare("SELECT uid FROM users WHERE api_key = ?");
		$stmt->bind_param("s", $api_key);
		$stmt->execute();
		$stmt->store_result();
		$num_rows = $stmt->num_rows;
		$stmt->close();
		return $num_rows > 0;
	}

	/* --- 'posts' table methods --- */

	/**
	 *	Creating new post
	 *	@param String $user_uid User UID to whom post belongs to
	 *	@param String $content Content of the post
	 *	@param Int $privacy Privacy mode (public, private, only friends, etc)
	 *	@param Int $num_likes Number of likes
	 *	@param Int $num_comments Number of comments
	 *	@param Int $num_shares Number of shares
	 */
	public function createPost($user_uid, $content, $privacy, $num_likes, $num_comments, $num_shares) {
		// Generating post uid
		$uid = $this->generateUID();
		if ($uid == NULL) {
			return NULL;
		}

		$stmt = $this->conn->prepare("INSERT INTO posts(uid, content, privacy, num_likes, num_comments, num_shares) VALUES(?,?,?,?,?,?)");
		$stmt->bind_param("ssiiii", $uid, $content, $privacy, $num_likes, $num_comments, $num_shares);
		$result = $stmt->execute();
		$stmt->close();

		if ($result) {
			// Post row created
			// Now assign the post to user
			$res = $this->createUserPost($user_uid, $uid);
			if ($res) {
				// Post created successfully 
				return $uid;
			} else {
				// Post failed to create
				return NULL;
			}
		} else {
			// Post failed to create
			return NULL;
		}
	}

	/**
	 *	Fetching single post
	 *	@param String $post_uid UID of the post
	 *	@param String $user_uid UID of the user
	 */
	public function getPost($post_uid, $user_uid) {
		$stmt = $this->conn->prepare("SELECT p.id, p.uid, p.content, p.privacy, p.num_likes, p.num_comments, p.num_shares, p.updated_at, p.created_at
									  FROM posts p, user_posts up WHERE p.uid = ? AND up.post_uid = p.uid AND up.user_uid = ?");
		$stmt->bind_param("ss", $post_uid, $user_uid);
		if ($stmt->execute()) {
			$post = $stmt->get_result()->fetch_assoc();
			$stmt->close();
			return $post;
		} else {
			return NULL;
		}
	}

	/**
	 *	Fetching all user posts
	 *	@param String $user_uid UID of the user
	 */
	public function getAllUserPosts($user_uid) {
		$stmt = $this->conn->prepare("SELECT p.* FROM posts p, user_posts up WHERE p.uid = up.post_uid AND up.user_uid = ?");
		$stmt->bind_param("s", $user_uid);
		$stmt->execute();
		$posts = $stmt->get_result();
		$stmt->close();
		return $posts;
	}

	/**
	 *	Updating post
	 *	@param String $post_uid UID of the post
	 *	@param String $user_uid UID of the user
	 *	@param String $content Content of the post
	 *	@param Int $privacy Privacy mode (public, private, only friends, etc) 
	 *	@param Int $num_likes Number of likes
	 *	@param Int $num_comments Number of comments
	 *	@param Int $num_shares Number of shares
	 */
	public function updatePost($post_uid, $user_uid, $content, $privacy, $num_likes, $num_comments, $num_shares) {
		$stmt = $this->conn->prepare("UPDATE posts p, user_posts up
									  SET p.content = ?, p.privacy = ?, p.num_likes = ?, p.num_comments = ?, p.num_shares = ?, p.updated_at = CURRENT_TIMESTAMP
									  WHERE p.uid = ? AND p.uid = up.post_uid AND up.user_uid = ?");
		$stmt->bind_param("siiiiss", $content, $privacy, $num_likes, $num_comments, $num_shares, $post_uid, $user_uid);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
		$stmt->close();
		return $num_affected_rows > 0;
	}

	/**
	 *	Deleting a post
	 *	@param String $post_uid UID of the post to delete
	 *	@param String $user_uid UID of the user
	 */
	public function deletePost($post_uid, $user_uid) {
		$stmt = $this->conn->prepare("DELETE p FROM posts p, user_posts up WHERE p.uid = ? AND up.post_uid = p.uid AND up.user_uid = ?");
		$stmt->bind_param("ss", $post_uid, $user_uid);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
		$stmt->close();
		return $num_affected_rows > 0;
	}

	/* --- 'looks' table methods --- */

	/**
	 *	Creating new look
	 *	@param String $user_uid User UID to whom look belongs to
	 *	@param String $title Title of the look
	 *	@param Int $privacy Privacy mode (public, private, only friends, etc)
	 * 	@param Int $num_items Number of items 
	 *	@param Int $num_likes Number of likes
	 *	@param Int $num_comments Number of comments
	 *	@param Int $num_shares Number of shares
	 */
	public function createLook($user_uid, $title, $privacy, $num_items, $num_likes, $num_comments, $num_shares) {
		// Generating look uid
		$uid = $this->generateUID();
		if ($uid == NULL) {
			return NULL;
		}

		$stmt = $this->conn->prepare("INSERT INTO looks(uid, title, privacy, num_items, num_likes, num_comments, num_shares) VALUES(?,?,?,?,?,?,?)");
		$stmt->bind_param("ssiiiii", $uid, $title, $privacy, $num_items, $num_likes, $num_comments, $num_shares);
		$result = $stmt->execute();
		$stmt->close();

		if ($result) {
			// Look row created
			// Now assign the look to user
			$res = $this->createUserLook($user_uid, $uid);
			if ($res) {
				// Look created successfully 
				return $uid;
			} else {
				// Look failed to create
				return NULL;
			}
		} else {
			// Look failed to create
			return NULL;
		}
	}

	/**
	 *	Fetching single look
	 *	@param String $look_uid UID of the look
	 *	@param String $user_uid UID of the user
	 */
	public function getLook($look_uid, $user_uid) {
		$stmt = $this->conn->prepare("SELECT l.id, l.uid, l.title, l.privacy, l.num_items, l.num_likes, l.num_comments, l.num_shares, l.updated_at, l.created_at
									  FROM looks l, user_looks ul WHERE l.uid = ? AND ul.look_uid = l.uid AND ul.user_uid = ?");
		$stmt->bind_param("ss", $look_uid, $user_uid);
		if ($stmt->execute()) {
			$look = $stmt->get_result()->fetch_assoc();
			$stmt->close();
			return $look;
		} else {
			return NULL;
		}
	}

	/**
	 *	Fetching all user looks
	 *	@param String $user_uid UID of the user
	 */
	public function getAllUserLooks($user_uid) {
		$stmt = $this->conn->prepare("SELECT l.* FROM looks l, user_looks ul WHERE l.uid = ul.look_uid AND ul.user_uid = ?");
		$stmt->bind_param("s", $user_uid);
		$stmt->execute();
		$looks = $stmt->get_result();
		$stmt->close();
		return $looks;
	}

	/**
	 *	Updating look
	 *	@param String $look_uid UID of the look
	 *	@param String $user_uid UID of the user
	 *	@param String $title Title of the look
	 *	@param Int $privacy Privacy mode (public, private, only friends, etc)
	 * 	@param Int $num_items Number of items 
	 *	@param Int $num_likes Number of likes
	 *	@param Int $num_comments Number of comments
	 *	@param Int $num_shares Number of shares
	 */
	public function updateLook($look_uid, $user_uid, $title, $privacy, $num_items, $num_likes, $num_comments, $num_shares) {
		$stmt = $this->conn->prepare("UPDATE looks l, user_looks ul
									  SET l.title = ?, l.privacy = ?, l.num_items = ?, l.num_likes = ?, l.num_comments = ?, l.num_shares = ?, l.updated_at = CURRENT_TIMESTAMP
									  WHERE l.uid = ? AND l.uid = ul.look_uid AND ul.user_uid = ?");
		$stmt->bind_param("siiiiiss", $title, $privacy, $num_items, $num_likes, $num_comments, $num_shares, $look_uid, $user_uid);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
		$stmt->close();
		return $num_affected_rows > 0;
	}

	/**
	 *	Deleting a look
	 *	@param String $look_uid UID of the look to delete
	 *	@param String $user_uid UID of the user
	 */
	public function deleteLook($look_uid, $user_uid) {
		$stmt = $this->conn->prepare("DELETE l FROM looks l, user_looks ul WHERE l.uid = ? AND ul.look_uid = l.uid AND ul.user_uid = ?");
		$stmt->bind_param("ss", $look_uid, $user_uid);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
		$stmt->close();
		return $num_affected_rows > 0;
	}

	/* --- 'items' table methods --- */

	/**
	 *	Creating new item
	 *	@param String $look_uid Look UID to whom item belongs to
	 *	@param String $title Title of the item
	 *	@param String $images A string with all item images urls
	 */
	public function createItem($look_uid, $title, $images) {
		// Generating item uid
		$uid = $this->generateUID();
		if ($uid == NULL) {
			return NULL;
		}

		$stmt = $this->conn->prepare("INSERT INTO items(uid, title, images) VALUES(?,?,?)");
		$stmt->bind_param("sss", $uid, $title, $images);
		$result = $stmt->execute();
		$stmt->close();

		if ($result) {
			// Item row created
			// Now assign the item to look
			$res = $this->createLookItem($look_uid, $uid);
			if ($res) {
				// Item created successfully 
				return $uid;
			} else {
				// Item failed to create
				return NULL;
			}
		} else {
			// Item failed to create
			return NULL;
		}
	}

	/**
	 *	Fetching single item
	 *	@param String $item_uid UID of the item
	 *	@param String $look_uid UID of the look
	 */
	public function getItem($item_uid, $look_uid) {
		$stmt = $this->conn->prepare("SELECT i.id, i.uid, i.title, i.images, i.updated_at, i.created_at
									  FROM items i, look_items li WHERE i.uid = ? AND li.item_uid = i.uid AND li.look_uid = ?");
		$stmt->bind_param("ss", $item_uid, $look_uid);
		if ($stmt->execute()) {
			$item = $stmt->get_result()->fetch_assoc();
			$stmt->close();
			return $item;
		} else {
			return NULL;
		}
	}

	/**
	 *	Fetching all look items
	 *	@param String $look_uid UID of the look
	 */
	public function getAllLookItems($look_uid) {
		$stmt = $this->conn->prepare("SELECT i.* FROM items i, look_items li WHERE i.uid = li.item_uid AND li.look_uid = ?");
		$stmt->bind_param("s", $look_uid);
		$stmt->execute();
		$items = $stmt->get_result();
		$stmt->close();
		return $items;
	}

	/**
	 *	Updating item
	 *	@param String $item_uid UID of the item
	 *	@param String $look_uid UID of the look
	 *	@param String $title Title of the item
	 *	@param String $images A string with all item images urls
	 */
	public function updateItem($item_uid, $look_uid, $title, $images) {
		$stmt = $this->conn->prepare("UPDATE items i, look_items li
									  SET i.title = ?, i.images = ?, i.updated_at = CURRENT_TIMESTAMP
									  WHERE i.uid = ? AND i.uid = li.item_uid AND li.look_uid = ?");
		$stmt->bind_param("ssss", $title, $images, $item_uid, $look_uid);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
		$stmt->close();
		return $num_affected_rows > 0;
	}

	/**
	 *	Deleting an item
	 *	@param String $item_uid UID of the item to delete
	 *	@param String $look_uid UID of the look
	 */
	public function deleteItem($item_uid, $look_uid) {
		$stmt = $this->conn->prepare("DELETE i FROM items i, look_items li WHERE i.uid = ? AND li.item_uid = i.uid AND li.look_uid = ?");
		$stmt->bind_param("ss", $item_uid, $look_uid);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
		$stmt->close();
		return $num_affected_rows > 0;
	}

	/* --- 'comments' table methods --- */

	/**
	 *	Creating new comment
	 *	@param String $entity_uid UID of the entity (post or look) to whom comment belongs to
	 *	@param Int $entity_type Type of the entity (post or look)
	 *	@param Int $type Type of the comment (comment or answer)
	 *	@param String $content A string with comment content
	 *	@param Int $num_likes Number of comment likes
	 */
	public function createComment($entity_uid, $entity_type, $type, $content, $num_likes) {
		// Generating comment uid
		$uid = $this->generateUID();
		if ($uid == NULL) {
			return NULL;
		}

		$stmt = $this->conn->prepare("INSERT INTO comments(uid, type, content, num_likes) VALUES(?,?,?,?)");
		$stmt->bind_param("sisi", $uid, $type, $content, $num_likes);
		$result = $stmt->execute();
		$stmt->close();

		if ($result) {
			// Comment row created
			// Now assign the comment to entity
			$res = false;
			if ($entity_type == COMMENT_ENTITY_POST) {
				$res = $this->createPostComment($entity_uid, $uid);
			} else if ($entity_type == COMMENT_ENTITY_LOOK) {
				$res = $this->createLookComment($entity_uid, $uid);
			}

			if ($res) {
				// Comment created successfully 
				return $uid;
			} else {
				// Comment failed to create
				return NULL;
			}
		} else {
			// Comment failed to create
			return NULL;
		}
	}

	/**
	 *	Fetching single comment
	 *	@param String $comment_uid UID of the comment
	 *	@param String $entity_uid UID of the entity (post or look)
	 *	@param Int $entity_type Type of the entity (post or look)
	 */
	public function getComment($comment_uid, $entity_uid, $entity_type) {
		if ($entity_type == COMMENT_ENTITY_POST) {
			$stmt = $this->conn->prepare("SELECT c.id, c.uid, c.type, c.content, c.num_likes, c.updated_at, c.created_at
									  FROM comments c, post_comments pc WHERE c.uid = ? AND pc.comment_uid = c.uid AND pc.post_uid = ?");
		} else if ($entity_type == COMMENT_ENTITY_LOOK) {
			$stmt = $this->conn->prepare("SELECT c.id, c.uid, c.type, c.content, c.num_likes, c.updated_at, c.created_at
									  FROM comments c, look_comments lc WHERE c.uid = ? AND lc.comment_uid = c.uid AND lc.look_uid = ?");
		}
		$stmt->bind_param("ss", $comment_uid, $entity_uid);
		if ($stmt->execute()) {
			$entity = $stmt->get_result()->fetch_assoc();
			$stmt->close();
			return $entity;
		} else {
			return NULL;
		}
	}

	/**
	 *	Fetching all entity comments
	 *	@param String $entity_uid UID of the entity (post or look)
	 *	@param Int $entity_type Type of the entity (post or look)
	 */
	public function getAllEntityComments($entity_uid, $entity_type) {
		if ($entity_type == COMMENT_ENTITY_POST) {
			$stmt = $this->conn->prepare("SELECT c.* FROM comments c, post_comments pc WHERE c.uid = pc.comment_uid AND pc.post_uid = ?");
		} else if ($entity_type == COMMENT_ENTITY_LOOK) {
			$stmt = $this->conn->prepare("SELECT c.* FROM comments c, look_comments lc WHERE c.uid = lc.comment_uid AND lc.look_uid = ?");
		}
		$stmt->bind_param("s", $entity_uid);
		$stmt->execute();
		$comments = $stmt->get_result();
		$stmt->close();
		return $comments;
	}

	/**
	 *	Updating comment
	 *	@param String $comment_uid UID of the comment
	 *	@param String $entity_uid UID of the entity (post or look)
	 *	@param Int $entity_type Type of the entity (post or look)
	 *	@param String $content A string with comment content
	 *	@param Int $num_likes Number of comment likes
	 */
	public function updateComment($comment_uid, $entity_uid, $entity_type, $content, $num_likes) {
		if ($entity_type == COMMENT_ENTITY_POST) {
			$stmt = $this->conn->prepare("UPDATE comments c, post_comments pc
									  SET c.content = ?, c.num_likes = ?, c.updated_at = CURRENT_TIMESTAMP
									  WHERE c.uid = ? AND c.uid = pc.comment_uid AND pc.post_uid = ?");
		} else if ($entity_type == COMMENT_ENTITY_LOOK) {
			$stmt = $this->conn->prepare("UPDATE comments c, look_comments lc
									  SET c.content = ?, c.num_likes = ?, c.updated_at = CURRENT_TIMESTAMP
									  WHERE c.uid = ? AND c.uid = lc.comment_uid AND lc.look_uid = ?");
		}

		$stmt->bind_param("siss", $content, $num_likes, $comment_uid, $entity_uid);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
		$stmt->close();
		return $num_affected_rows > 0;
	}

	/**
	 *	Deleting a comment
	 *	@param String $comment_uid UID of the comment to delete
	 *	@param String $entity_uid UID of the entity (post or look)
	 *	@param Int $entity_type Type of the entity (post or look)
	 */
	public function deleteComment($comment_uid, $entity_uid, $entity_type) {
		if ($entity_type == COMMENT_ENTITY_POST) {
			$stmt = $this->conn->prepare("DELETE c FROM comments c, post_comments pc
										  WHERE c.uid = ? AND pc.comment_uid = c.uid AND pc.post_uid = ?");
		} else if ($entity_type == COMMENT_ENTITY_LOOK) {
			$stmt = $this->conn->prepare("DELETE c FROM comments c, look_comments lc
										  WHERE c.uid = ? AND lc.comment_uid = c.uid AND lc.look_uid = ?");
		}
		$stmt->bind_param("ss", $comment_uid, $entity_uid);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
		$stmt->close();
		return $num_affected_rows > 0;
	}

	/* --- 'user_posts' table methods --- */

	/**
	 *	Function to assign a post to user
	 *	@param String $user_uid UID of the user
	 *	@param String $post_uid UID of the post
	 */
	public function createUserPost($user_uid, $post_uid) {
		$stmt = $this->conn->prepare("INSERT INTO user_posts(user_uid, post_uid) VALUES(?, ?)");
		$stmt->bind_param("ss", $user_uid, $post_uid);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	/* --- 'user_looks' table methods --- */

	/**
	 *	Function to assign a look to user
	 *	@param String $user_uid UID of the user
	 *	@param String $look_uid UID of the look
	 */
	public function createUserLook($user_uid, $look_uid) {
		$stmt = $this->conn->prepare("INSERT INTO user_looks(user_uid, look_uid) VALUES(?, ?)");
		$stmt->bind_param("ss", $user_uid, $look_uid);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	/* --- 'look_items table methods --- */

	/**
	 *	Function to assign an item to a look
	 *	@param String $look_uid UID of the look
	 *	@param String $item_uid UID of the item
	 */
	public function createLookItem($look_uid, $item_uid) {
		$stmt = $this->conn->prepare("INSERT INTO look_items(look_uid, item_uid) VALUES(?, ?)");
		$stmt->bind_param("ss", $look_uid, $item_uid);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	/* --- 'look_comments table methods --- */

	/**
	 *	Function to assign a comment to a look
	 *	@param String $look_uid UID of the look
	 *	@param String $comment_uid UID of the comment
	 */
	public function createLookComment($look_uid, $comment_uid) {
		$stmt = $this->conn->prepare("INSERT INTO look_comments(look_uid, comment_uid) VALUES(?, ?)");
		$stmt->bind_param("ss", $look_uid, $comment_uid);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	/* --- 'post_comments table methods --- */

	/**
	 *	Function to assign a comment to a post
	 *	@param String $post_uid UID of the post
	 *	@param String $comment_uid UID of the comment
	 */
	public function createPostComment($post_uid, $comment_uid) {
		$stmt = $this->conn->prepare("INSERT INTO post_comments(post_uid, comment_uid) VALUES(?, ?)");
		$stmt->bind_param("ss", $post_uid, $comment_uid);
		$result = $stmt->execute();
		$stmt->close();
		return $result;
	}

	/**
	 *	Generating random unique UUID string
	 */
	private function generateUID() {
		try {
			$uuid = Uuid::uuid4();
			return $uuid;
		} catch(UnsatisfiedDependencyException $e) {
			// Some dependency was no met. Either the method cannot be called on a
			// 32-bit system, or it can, but it relies on Moontoast\Math to be present
			echo 'Caught exception: ' . $e->getMessage() . "\n";
			return NULL;
		}
	}
}

?>
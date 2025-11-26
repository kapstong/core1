<?php
/**
 * Customer Model
 */

require_once __DIR__ . '/../config/database.php';

class Customer {
    private $db;
    private $table = 'customers';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data) {
        $query = "INSERT INTO {$this->table}
                  (email, password_hash, first_name, last_name, phone, date_of_birth, gender,
                   email_verification_token, email_verified)
                  VALUES
                  (:email, :password_hash, :first_name, :last_name, :phone, :date_of_birth, :gender,
                   :email_verification_token, :email_verified)";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password_hash', $data['password_hash']);
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':last_name', $data['last_name']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':date_of_birth', $data['date_of_birth']);
        $stmt->bindParam(':gender', $data['gender']);

        // Handle email verification token (allow null)
        $emailVerificationToken = $data['email_verification_token'] ?? null;
        $stmt->bindParam(':email_verification_token', $emailVerificationToken);

        // Handle email verified status (default to 0)
        $emailVerified = isset($data['email_verified']) ? (int)$data['email_verified'] : 0;
        $stmt->bindParam(':email_verified', $emailVerified, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return $this->findById($this->db->lastInsertId());
        }

        return false;
    }

    public function findById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmail($email) {
        $query = "SELECT * FROM {$this->table} WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $query = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($query);

        return $stmt->execute($params);
    }

    public function updateLastLogin($id) {
        $query = "UPDATE {$this->table} SET last_login = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function generateOrderNumber() {
        $prefix = 'ORD-' . date('Y') . '-';
        $query = "SELECT order_number FROM customer_orders
                  WHERE order_number LIKE :prefix
                  ORDER BY id DESC LIMIT 1";

        $stmt = $this->db->prepare($query);
        $searchPrefix = $prefix . '%';
        $stmt->bindParam(':prefix', $searchPrefix);
        $stmt->execute();

        $result = $stmt->fetch();

        if ($result) {
            $lastNumber = intval(str_replace($prefix, '', $result['order_number']));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    public function getAddresses($customerId) {
        $query = "SELECT * FROM customer_addresses WHERE customer_id = :customer_id ORDER BY is_default DESC, created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addAddress($customerId, $addressData) {
        $query = "INSERT INTO customer_addresses
                  (customer_id, address_type, is_default, first_name, last_name, company,
                   address_line_1, address_line_2, city, state, postal_code, country, phone)
                  VALUES
                  (:customer_id, :address_type, :is_default, :first_name, :last_name, :company,
                   :address_line_1, :address_line_2, :city, :state, :postal_code, :country, :phone)";

        $stmt = $this->db->prepare($query);

        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':address_type', $addressData['address_type']);
        $stmt->bindValue(':is_default', $addressData['is_default'], PDO::PARAM_BOOL);
        $stmt->bindValue(':first_name', $addressData['first_name']);
        $stmt->bindValue(':last_name', $addressData['last_name']);
        $stmt->bindValue(':company', $addressData['company']);
        $stmt->bindValue(':address_line_1', $addressData['address_line_1']);
        $stmt->bindValue(':address_line_2', $addressData['address_line_2']);
        $stmt->bindValue(':city', $addressData['city']);
        $stmt->bindValue(':state', $addressData['state']);
        $stmt->bindValue(':postal_code', $addressData['postal_code']);
        $stmt->bindValue(':country', $addressData['country']);
        $stmt->bindValue(':phone', $addressData['phone']);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    public function getCart($customerId = null, $sessionId = null) {
        $query = "SELECT sc.*, p.name, p.sku, p.selling_price, p.image_url, i.quantity_available
                  FROM shopping_cart sc
                  INNER JOIN products p ON sc.product_id = p.id
                  LEFT JOIN inventory i ON sc.product_id = i.product_id
                  WHERE 1=1";

        $params = [];

        if ($customerId) {
            $query .= " AND sc.customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        } elseif ($sessionId) {
            $query .= " AND sc.session_id = :session_id";
            $params[':session_id'] = $sessionId;
        }

        $query .= " ORDER BY sc.created_at DESC";

        $stmt = $this->db->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addToCart($customerId, $sessionId, $productId, $quantity) {
        // Check if item already exists in cart
        $existingQuery = "SELECT id, quantity FROM shopping_cart WHERE ";
        $params = [];

        if ($customerId) {
            $existingQuery .= "customer_id = :customer_id AND product_id = :product_id";
            $params[':customer_id'] = $customerId;
        } else {
            $existingQuery .= "session_id = :session_id AND product_id = :product_id";
            $params[':session_id'] = $sessionId;
        }

        $params[':product_id'] = $productId;

        $existingStmt = $this->db->prepare($existingQuery);
        $existingStmt->execute($params);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update quantity
            $newQuantity = $existing['quantity'] + $quantity;
            $updateQuery = "UPDATE shopping_cart SET quantity = :quantity, updated_at = NOW() WHERE id = :id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindValue(':quantity', $newQuantity, PDO::PARAM_INT);
            $updateStmt->bindValue(':id', $existing['id'], PDO::PARAM_INT);
            return $updateStmt->execute();
        } else {
            // Insert new item
            $insertQuery = "INSERT INTO shopping_cart (customer_id, session_id, product_id, quantity)
                           VALUES (:customer_id, :session_id, :product_id, :quantity)";
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
            $insertStmt->bindValue(':session_id', $sessionId);
            $insertStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $insertStmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
            return $insertStmt->execute();
        }
    }

    public function updateCartItem($customerId, $sessionId, $productId, $quantity) {
        $query = "UPDATE shopping_cart SET quantity = :quantity, updated_at = NOW() WHERE ";
        $params = [':quantity' => $quantity];

        if ($customerId) {
            $query .= "customer_id = :customer_id AND product_id = :product_id";
            $params[':customer_id'] = $customerId;
        } else {
            $query .= "session_id = :session_id AND product_id = :product_id";
            $params[':session_id'] = $sessionId;
        }

        $params[':product_id'] = $productId;

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function removeFromCart($customerId, $sessionId, $productId) {
        $query = "DELETE FROM shopping_cart WHERE ";
        $params = [];

        if ($customerId) {
            $query .= "customer_id = :customer_id AND product_id = :product_id";
            $params[':customer_id'] = $customerId;
        } else {
            $query .= "session_id = :session_id AND product_id = :product_id";
            $params[':session_id'] = $sessionId;
        }

        $params[':product_id'] = $productId;

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function clearCart($customerId, $sessionId) {
        $query = "DELETE FROM shopping_cart WHERE ";
        $params = [];

        if ($customerId) {
            $query .= "customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        } else {
            $query .= "session_id = :session_id";
            $params[':session_id'] = $sessionId;
        }

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function transferCartToCustomer($sessionId, $customerId) {
        $query = "UPDATE shopping_cart SET customer_id = :customer_id, session_id = NULL WHERE session_id = :session_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindParam(':session_id', $sessionId);
        return $stmt->execute();
    }
}

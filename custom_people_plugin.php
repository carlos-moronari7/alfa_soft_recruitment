<?php
/*
Plugin Name: Custom People Management
Description: A WordPress plugin for managing people and their contacts.
Version: 1.0
Author: Carlos Rodrigues
*/


class Custom_People_Management_Plugin {
    
    function __construct() {
        add_action('init', array($this, 'custom_post_type'));
        add_action('admin_menu', array($this, 'add_plugin_pages'));
        add_action('admin_post_add_person', array($this, 'handle_add_person')); // Handle form submission for adding a person
        add_action('admin_post_add_contact', array($this, 'handle_add_contact')); // Handle form submission for adding a contact
        add_action('admin_post_delete_contact', array($this, 'handle_delete_contact'));
         add_action('admin_post_update_contact', array($this, 'handle_update_contact'));
        add_action('admin_post_update_person', array($this, 'handle_update_person'));
        add_action('admin_post_delete_person', array($this, 'handle_delete_person'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall')); // Using __CLASS__ for static method
    }
    
    function activate() {
        // Activation logic here
    }
     
    function deactivate() {
        // Deactivation logic here
    }
     
    static function uninstall() { // Making the method static
        // Uninstallation logic here
    }
     
    function custom_post_type() {
        register_post_type('person', [
            'public' => true,
            'label' => 'People',
            'supports' => ['title', 'editor']
        ]);
    }
    
    
     function edit_contact_page() {
    // Check if ID parameter is set
    if (isset($_GET['id'])) {
        $contact_id = intval($_GET['id']);
        global $wpdb;
        // Retrieve contact's information from the database
        $contact = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}contacts WHERE id = %d", $contact_id));
        if ($contact) {
            // Get countries list
            $countries_list = $this->get_countries_list();
            // Display the edit contact form with pre-filled values
            ?>
            <div class="wrap">
                <h2>Edit Contact</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="update_contact">
                    <input type="hidden" name="contact_id" value="<?php echo $contact_id; ?>"> <!-- Hidden input for contact's ID -->
                    <?php wp_nonce_field('update_contact'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="contact_country_code">Country</label></th>
                            <td>
                                <!-- Display the country code dropdown -->
                                <select name="contact_country_code" id="contact_country_code" required>
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries_list as $country_code => $country_info) : ?>
                                        <option value="<?php echo $country_code; ?>" <?php selected($contact->country_code, $country_code); ?>><?php echo $country_info; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="contact_number">Number</label></th>
                            <td><input type="text" name="contact_number" id="contact_number" value="<?php echo esc_attr($contact->number); ?>" required></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="Update Contact">
                    </p>
                </form>
            </div>
            <?php
        } else {
            echo '<p>Contact not found.</p>';
        }
    } else {
        echo '<p>Contact ID not provided.</p>';
    }
}


    function handle_update_contact() {
        if (isset($_POST['contact_id']) && isset($_POST['contact_number'])) {
            $contact_id = intval($_POST['contact_id']);
            $number = sanitize_text_field($_POST['contact_number']);
            global $wpdb;

            // Update contact's information in the database using SQL query
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->prefix}contacts SET number = %s WHERE id = %d",
                $number,
                $contact_id
            );
            $updated = $wpdb->query($sql);

            // Check if the update was successful
            if ($updated !== false) {
                // Redirect back to the edit contact page with a success message
                wp_redirect(admin_url("admin.php?page=contacts&id=$contact_id&success=1"));
                exit;
            } else {
                // Redirect back to the edit contact page with an error message
                wp_redirect(admin_url("admin.php?page=contacts&id=$contact_id&error=1"));
                exit;
            }
        } else {
            // Redirect back to the edit contact page with an error message if data is missing
            wp_redirect(admin_url('admin.php?page=contacts&error=1'));
            exit;
        }
    }

    function handle_add_person() {
        if (isset($_POST['person_name']) && isset($_POST['person_email'])) {
            $name = sanitize_text_field($_POST['person_name']);
            $email = sanitize_email($_POST['person_email']);

            // Insert person data into the database
            $this->insert_person($name, $email);

            // Redirect back to the page with a success message
            wp_redirect(admin_url('admin.php?page=add_new_person&success=1'));
            exit;
        } else {
            // Redirect back with an error message if data is missing
            wp_redirect(admin_url('admin.php?page=add_new_person&error=1'));
            exit;
        }
    }
    
   function contacts_page() {
    global $wpdb;

    // Retrieve the list of contacts from the database
   $contacts = $wpdb->get_results("SELECT contacts.id AS contact_id, people.name AS person_name, contacts.number, contacts.country_code 
                                FROM {$wpdb->prefix}contacts AS contacts 
                                INNER JOIN {$wpdb->prefix}people AS people 
                                ON contacts.person_id = people.id");

    // Display the Contacts page content
    echo '<div class="wrap">';
    echo '<h2>Contacts</h2>';

    // Display a table to show the list of contacts
    echo '<table class="wp-list-table widefat fixed striped">';
echo '<tbody>';

    // Loop through each contact and display its details in a table row
    // Displaying contacts in the table with contact ID
echo '<thead><tr><th>Contact ID</th><th>Person Name</th><th>Contact Number</th><th>Actions</th></tr></thead>';

foreach ($contacts as $contact) {
    echo '<tr>';
    echo '<td>' . esc_html($contact->contact_id) . '</td>'; // Displaying contact ID
    echo '<td>' . esc_html($contact->person_name) . '</td>';
    echo '<td>' . esc_html($contact->country_code . $contact->number) . '</td>';
    echo '<td><a href="' . admin_url("admin.php?page=edit_contact&id=$contact->contact_id") . '">Edit</a> | <a href="' . admin_url("admin-post.php?action=delete_contact&id=$contact->contact_id") . '">Delete</a></td>';
    echo '</tr>';
}


    echo '</tbody></table>';
    echo '</div>';
}



   function handle_add_contact() {
    ob_start(); // Start output buffering
    
    if (isset($_POST['person_id'], $_POST['contact_country_code'], $_POST['contact_number'])) {
        $person_id = intval($_POST['person_id']);
        $country_code_full = $_POST['contact_country_code'];
        $number = sanitize_text_field($_POST['contact_number']);

        // Check if the country code is empty
        if (empty($country_code_full)) {
            // Output error message if the country code is empty
            echo "Error: Country code is empty.";
            ob_end_flush(); // End output buffering and flush buffer
            return;
        }
        
        // Insert contact data into the database
        $inserted = $this->insert_contact($person_id, $country_code_full, $number);

        // Check if insertion was successful
        if ($inserted) {
            // Output success message
            echo "Contact added successfully!";
        } else {
            // Output error message if insertion failed
            echo "Error: Failed to add contact to the database.";
        }
    } else {
        // Output error message if data is missing
        echo "Error: Missing data.";
    }

    // Redirect
    ob_end_clean(); // Clear buffer without sending output
    wp_redirect(admin_url('admin.php?page=add_new_contact'));
    exit; // Ensure script execution stops after redirection
}
function handle_delete_contact() {
    if (isset($_GET['id'])) {
        $contact_id = intval($_GET['id']); // Sanitize the contact ID
        global $wpdb;
        
        // Delete the contact from the database based on the contact ID
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'contacts',
            array('id' => $contact_id),
            array('%d')
        );
        
        if ($deleted) {
            // Redirect back to the Contacts page with a success message
            wp_redirect(admin_url('admin.php?page=contacts&success=1'));
            exit;
        } else {
            // Redirect back with an error message if deletion failed
            wp_redirect(admin_url('admin.php?page=contacts&error=1'));
            exit;
        }
    } else {
        // Redirect back with an error message if contact ID is not provided
        wp_redirect(admin_url('admin.php?page=contacts&error=1'));
        exit;
    }
}








    function insert_contact($person_id, $country_code, $number) {
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'contacts', // Table name with prefix
            array(
                'person_id' => $person_id,
                'country_code' => $country_code,
                'number' => $number
            ),
            array('%d', '%s', '%s')
        );

        if ($result === false) {
            error_log('Error inserting contact: ' . $wpdb->last_error);
        }

        return $result !== false; // Return true if insertion was successful, false otherwise
    }

    function insert_person($name, $email) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'people', // Table name with prefix
            array(
                'name' => $name,
                'email' => $email
            ),
            array('%s', '%s')
        );
    }

    function get_countries_list() {
        $url = 'https://restcountries.com/v3.1/all';
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            error_log('Error fetching data: ' . $response->get_error_message());
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $countries_data = json_decode($body, true);

        if (!$countries_data) {
            error_log('Error
        // decoding JSON data');
            return array();
        }

        $countries_list = array();
        foreach ($countries_data as $country_code => $country_info) {
            $country_name = $country_info['name']['common'];
            $calling_code_root = isset($country_info['idd']['root']) ? $country_info['idd']['root'] : '';
            $calling_code_suffix = isset($country_info['idd']['suffixes'][0]) ? $country_info['idd']['suffixes'][0] : '';
            $calling_code = $calling_code_root . $calling_code_suffix; // Concatenate root and suffix
            $countries_list[$calling_code] = $country_name . ' (' . $calling_code . ')';
        }

        return $countries_list;
    }

        function add_plugin_pages() {
        add_menu_page('People', 'People', 'manage_options', 'people', array($this, 'people_page'));
        add_submenu_page('people', 'Add New Person', 'Add New', 'manage_options', 'add_new_person', array($this, 'add_new_person_page'));
        add_submenu_page(null, 'Edit Person', 'Edit', 'manage_options', 'edit_person', array($this, 'edit_person_page'));
        add_submenu_page('people', 'Contacts', 'Contacts', 'manage_options', 'contacts', array($this, 'contacts_page')); // New submenu for Contacts
        add_submenu_page('people', 'Add New Contact', 'Add New Contact', 'manage_options', 'add_new_contact', array($this, 'add_new_contact_page'));
        add_submenu_page(null, 'Edit Contact', 'Edit Contact', 'manage_options', 'edit_contact', array($this, 'edit_contact_page'));
    }
    


    function add_new_person_page() {
    // Display the page for adding a new person
    ?>
    <div class="wrap">
        <h2>Add New Person</h2>
        <?php if (isset($_GET['success'])) : ?>
            <div class="updated"><p>Person added successfully!</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])) : ?>
            <div class="error"><p>Error: Please fill out all fields correctly.</p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="add_person">
            <?php wp_nonce_field('add_person'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="person_name">Name</label></th>
                    <td><input type="text" name="person_name" id="person_name" pattern=".{6,}" title="Name must be at least 6 characters" required></td>
                </tr>
                <tr>
                    <th><label for="person_email">Email</label></th>
                    <td><input type="email" name="person_email" id="person_email" required></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Add Person">
            </p>
        </form>
    </div>
    <?php
}

    

    
    function handle_delete_person() {
        if (isset($_GET['id'])) {
            $person_id = intval($_GET['id']);
            global $wpdb;
            
            // Delete the person from the database
           // Soft delete the person by updating the deleted_at column
$deleted = $wpdb->update(
    $wpdb->prefix . 'people',
    array('deleted_at' => current_time('mysql', 1)), // Set deleted_at to current timestamp
    array('id' => $person_id),
    array('%s'), // Format for deleted_at column
    array('%d') // Format for id column
);

            
            if ($deleted) {
                // Redirect back to the People page with a success message
                wp_redirect(admin_url('admin.php?page=people&success=1'));
                exit;
            } else {
                // Redirect back with an error message if deletion failed
                wp_redirect(admin_url('admin.php?page=people&error=1'));
                exit;
            }
        } else {
            // Redirect back with an error message if person ID is not provided
            wp_redirect(admin_url('admin.php?page=people&error=1'));
            exit;
        }
    }

    function people_page() {
        global $wpdb;

        // Retrieve the list of people from the database
        // Retrieve the list of people from the database where deleted_at is NULL
$people = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}people WHERE deleted_at IS NULL");


        // Display the People page content
        echo '<div class="wrap">';
        echo '<h2>People</h2>';

        // Display a table to show the list of people
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Actions</th></tr></thead>';
        echo '<tbody>';

        // Loop through each person and display their details in a table row
        foreach ($people as $person) {
            echo '<tr>';
            echo '<td>' . esc_html($person->id) . '</td>';
            echo '<td>' . esc_html($person->name) . '</td>';
            echo '<td>' . esc_html($person->email) . '</td>';
            echo '<td><a href="' . admin_url("admin.php?page=edit_person&id=$person->id") . '">Edit</a> | <a href="' . admin_url("admin-post.php?action=delete_person&id=$person->id") . '">Delete</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    function edit_person_page() {
        // Check if ID parameter is set
        if (isset($_GET['id'])) {
            $person_id = intval($_GET['id']);
            global $wpdb;
            // Retrieve person's information from the database
            $person = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}people WHERE id = %d", $person_id));
            if ($person) {
                // Display the edit person form with pre-filled values
                ?>
                <div class="wrap">
                    <h2>Edit Person</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="update_person">
                        <input type="hidden" name="person_id" value="<?php echo $person_id; ?>"> <!-- Hidden input for person's ID -->
                        <?php wp_nonce_field('update_person'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="person_name">Name</label></th>
                                <td><input type="text" name="person_name" id="person_name" value="<?php echo esc_attr($person->name); ?>" required></td>
                            </tr>
                            <tr>
                                <th><label for="person_email">Email</label></th>
                                <td><input type="email" name="person_email" id="person_email" value="<?php echo esc_attr($person->email); ?>" required></td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary" value="Update Person">
                        </p>
                    </form>
                </div>
                <?php
            } else {
                echo '<p>Person not found.</p>';
            }
        } else
{
            echo '<p>Person ID not provided.</p>';
        }
    }

    function handle_update_person() {
        if (isset($_POST['person_id']) && isset($_POST['person_name']) && isset($_POST['person_email'])) {
            $person_id = intval($_POST['person_id']);
            $name = sanitize_text_field($_POST['person_name']);
            $email = sanitize_email($_POST['person_email']);
            global $wpdb;

            // Update person's information in the database using SQL query
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->prefix}people SET name = %s, email = %s WHERE id = %d",
                $name,
                $email,
                $person_id
            );
            $updated = $wpdb->query($sql);

            // Check if the update was successful
            if ($updated !== false) {
                // Redirect back to the edit person page with a success message
                wp_redirect(admin_url("admin.php?page=people&id=$person_id&success=1"));
                exit;
            } else {
                // Redirect back to the edit person page with an error message
                wp_redirect(admin_url("admin.php?page=people&id=$person_id&error=1"));
                exit;
            }
        } else {
            // Redirect back to the edit person page with an error message if data is missing
            wp_redirect(admin_url('admin.php?page=people&error=1'));
            exit;
        }
    }

    function add_new_contact_page() {
        // Get countries list
        $countries_list = $this->get_countries_list();
        
        // Get the list of people from the database
        global $wpdb;
        $people = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}people");
        ?>
        <div class="wrap">
            <h2>Add New Contact</h2>
            <?php if (isset($_GET['success'])) : ?>
                <div class="updated"><p>Contact added successfully!</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])) : ?>
                <div class="error"><p>Error: Please fill out all fields.</p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="add_contact">
                <?php wp_nonce_field('add_contact'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="contact_person_id">Person</label></th>
                        <td>
                            <select name="person_id" id="contact_person_id" required>
                                <option value="">Select Person</option>
                                <?php foreach ($people as $person) : ?>
                                    <option value="<?php echo $person->id; ?>"><?php echo $person->name . ' (ID: ' . $person->id . ')'; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="contact_country_code">Country</label></th>
                        <td>
                            <select name="contact_country_code" id="contact_country_code" required>
                                <option value="">Select Country</option>
                                <?php foreach ($countries_list as $country_code => $country_info) : ?>
                                   <option value="<?php echo $country_code; ?>"><?php echo $country_info; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="contact_number">Number</label></th>
                        <td><input type="text" name="contact_number" id="contact_number" pattern="\d{9}" required></td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Add Contact">
                </p>
            </form>

        </div>

        <?php
    }

}

// Instantiate the class
$custom_people_management_plugin = new Custom_People_Management_Plugin();

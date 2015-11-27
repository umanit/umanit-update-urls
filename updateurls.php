<?php
/*
Plugin Name: UmanIT Update URLs
Description: Updates all URLs in the database after moving your site from one domain to another.
Author: UmanIT
Author URI: http://www.umanit.fr/
Author Email: vrobic@umanit.fr
Version: 1.0
License: GPLv2 or later
Text Domain: umanit-updateurls
Domain Path: /languages
*/

if ( !function_exists( 'add_action' ) )
{
    echo "Oops! This page cannot be accessed directly.";
    exit;
}

/**
 * Adds a link in the admin menu
 * 
 */
function UmanITUpdateURL_add_options_page()
{
    add_options_page("UmanIT Update URLs", "Update URLs", 'manage_options', basename(__FILE__), 'UmanITUpdateURL_options_page');
}

/**
 * Loads the translation files
 * 
 */
function UmanITUpdateURL_load_textdomain()
{
    load_plugin_textdomain( 'umanit-updateurls', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action('admin_init', 'UmanITUpdateURL_load_textdomain');

/**
 * Calls external CSS and Javascript files
 * 
 */
function UmanITUpdateURL_head()
{
    $pluginPath = get_option('siteurl').'/wp-content/plugins/'.basename(dirname(__FILE__));
    
    echo '<link rel="stylesheet" type="text/css" href="'.$pluginPath.'/css/style.css" />'."\n";
    echo '<script type="text/javascript" src="'.$pluginPath.'/js/listeners.js"></script>'."\n";
}
add_action('admin_head', 'UmanITUpdateURL_head');

/**
 * Adds a link in the management (tools) menu
 * 
 */
function UmanITUpdateURL_add_management_pages()
{
    if (function_exists('add_management_page'))
    {
        add_management_page(
                            __("Update URLs", 'umanit-updateurls'), // Title for the menu link
                            __("Update URLs", 'umanit-updateurls'), // Title of the page
                            'manage_options', // Capabilities
                            __FILE__,
                            'UmanITUpdateURL_options_page' // Function to call
                           );
    }		
}
add_action('admin_menu', 'UmanITUpdateURL_add_management_pages');

/**
 * Function to recursively iterate through an array and replace all occurrences of the search string with the replacement string
 * 
 * @param string $search
 * @param string $replace
 * @param array $subject
 * @return array $array
 */
function recursive_str_replace($search, $replace, $subject)
{
    $output = array();
    
    foreach ($subject as $key => $value)
    {
        if (is_string($value))
        {
            $output[$key] = str_replace($search, $replace, $value);
        }
        elseif (is_array($value))
        {
            $output[$key] = recursive_str_replace($search, $replace, $value);
        }
    }
    
    return $output;
}

/**
 * The plugin's page
 * 
 */
function UmanITUpdateURL_options_page()
{
    global $wpdb;
    $urlsToUpdate = array();
    
    // Example URLs
    define('OLDURL_EXAMPLE', "http://www.oldurl.com");
    define('NEWURL_EXAMPLE', "http://www.newurl.com");
    
    function updateURLs($options, $oldurl, $newurl)
    {
        global $wpdb;
        $results = array();
        
        // Simple queries to update URLs that are not serialized in the database
        $queries = array(
                         'options' =>     array(
                                                'query' => "UPDATE $wpdb->options SET option_value = REPLACE(option_value, '$oldurl', '$newurl') WHERE option_name='siteurl' OR option_name='home' OR option_name='download_path_url' OR option_name='download_page_url';",
                                                'label' => __("Global options", 'umanit-updateurls')
                                               ),
                         'content' =>     array(
                                                'query' => "UPDATE $wpdb->posts SET post_content = REPLACE(post_content, '$oldurl', '$newurl'), pinged = REPLACE(pinged, '$oldurl', '$newurl');",
                                                'label' => __("Content items (posts, pages, custom post types, revisions)", 'umanit-updateurls')
                                               ),
                         'excerpts' =>    array(
                                                'query' => "UPDATE $wpdb->posts SET post_excerpt = REPLACE(post_excerpt, '$oldurl', '$newurl');",
                                                'label' => __("Excerpts", 'umanit-updateurls')
                                               ),
                         'attachments' => array(
                                                'query' => "UPDATE $wpdb->posts SET guid = REPLACE(guid, '$oldurl', '$newurl') WHERE post_type = 'attachment';",
                                                'label' => __("Attachments", 'umanit-updateurls')
                                               ),
                         'guids' =>       array(
                                                'query' => "UPDATE $wpdb->posts SET guid = REPLACE(guid, '$oldurl', '$newurl');",
                                                'label' => __("GUIDs", 'umanit-updateurls')
                                               )
                        );
        
        foreach ($options as $option)
        {
            if (isset($queries[$option]))
            {
                $result = $wpdb->query( $queries[$option]['query'] );

                $results[$option] = array(
                                          'count' => $result,
                                          'label' => $queries[$option]['label']
                                         );
            }

            
            /* The custom fields can be stored in two tables : postmeta and options.
             * In these tables, some fields can be serialized. We can't use a single query since it would break the serialization.
             */
            if ($option == 'custom')
            {
                $rawRows = array();

                // Postmeta table
                $rawRows['postmeta'] = $wpdb->get_results("SELECT meta_id, meta_value FROM ".$wpdb->postmeta.";");
                
                // Options table
                $rawRows['options'] = $wpdb->get_results("SELECT option_id, option_value FROM ".$wpdb->options.";");
                
                foreach ($rawRows as $table_name_without_prefix => $rows)
                {
                    $table_name  = ($table_name_without_prefix == 'postmeta') ? $wpdb->postmeta : $wpdb->options;
                    
                    foreach ($rows as $row)
                    {
                        $field_id    = ($table_name == $wpdb->postmeta) ? 'meta_id' : 'option_id';
                        $field_key = ($table_name == $wpdb->postmeta) ? 'meta_value' : 'option_value';
                        

                        // Convert the StdClass object to an Array
                        $rowAsArray = array();
                        foreach ($row as $column => $value)
                        {
                            $rowAsArray[$column] = $value;
                        }
                        
                        if (strpos($rowAsArray[$field_key], $oldurl)!==false)
                        {
                            
                            // Unserialize the value
                            $rowAsArray[$field_key] = maybe_unserialize($rowAsArray[$field_key]);
                            
                            // Sometimes the array is broken and maybe_unserialize returns false
                            if ($rowAsArray[$field_key])
                            {
                                
                                // Recursively update the URLs in this unserialized array
                                if (is_array($rowAsArray[$field_key]))
                                {
                                    // Apply a recursive str_replace
                                    $rowAsArray[$field_key] = recursive_str_replace($oldurl, $newurl, $rowAsArray[$field_key]);
                                    
                                    // Serialize new value
                                    $rowAsArray[$field_key] = maybe_serialize($rowAsArray[$field_key]);
                                }
                                // Else, it's a string. We can easily update the URLs
                                elseif (is_string($rowAsArray[$field_key]))
                                {
                                    $rowAsArray[$field_key] = str_replace($oldurl, $newurl, $rowAsArray[$field_key]);
                                }
                                
                                // Now the value must be a string
                                if (is_string($rowAsArray[$field_key]))
                                {
                                    // We do not use a handmade query since it could break because of single-quotes
                                    $result = $wpdb->update(
                                                            $table_name,
                                                            array($field_key => $rowAsArray[$field_key]), // The field to update
                                                            array($field_id => $rowAsArray[$field_id] ) // The where clause
                                                           );
                                    
                                    $results[$table_name_without_prefix]['count'] = isset($results[$table_name_without_prefix]['count']) && $results[$table_name_without_prefix]['count']>0 ? $results[$table_name_without_prefix]['count'] : 0;
                                    $results[$table_name_without_prefix] = array(
                                                                                 'count' => $results[$table_name_without_prefix]['count']+$result,
                                                                                 'label' => ($table_name_without_prefix=='postmeta') ? __("Custom fields", 'umanit-updateurls') : __("Global options", 'umanit-updateurls')
                                                                                );
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $results;			
    }
    
    // Retrieve the posted URLs ************************************************
    if (isset($_POST['UmanITUpdateURLs_oldurl']) && isset($_POST['UmanITUpdateURLs_newurl']))
    {
        // If esc_attr is available, use it
        if (function_exists('esc_attr'))
        {
            $oldurl = esc_attr(trim($_POST['UmanITUpdateURLs_oldurl']));
            $newurl = esc_attr(trim($_POST['UmanITUpdateURLs_newurl']));
        }
        else // Use the deprecated version of esc_attr
        {
            $oldurl = attribute_escape(trim($_POST['UmanITUpdateURLs_oldurl']));
            $newurl = attribute_escape(trim($_POST['UmanITUpdateURLs_newurl']));
        }
    }
    
    // Security : check that the WP Nonce is defined ***************************
    if (
        isset( $_POST['UmanITUpdateURLs_settings_submit'] ) &&
        !check_admin_referer('UmanITUpdateURLs_submit', 'UmanITUpdateURLs_nonce')
       )
    {
    ?>
        <div id="message" class="updateurls error fade">
            <p><strong><?php _e("Security error", 'umanit-updateurls'); ?></strong></p>
            <p><?php _e("The request seems to come from an external source.", 'umanit-updateurls'); ?></p>
        </div>
        <?php
    }
    else
    {
        // The query to update the siteurl option
        if ( isset( $_POST['UmanITUpdateURLs_runquery_submit'] ) )
        {
            // If esc_attr is available, use it
            if (function_exists('esc_attr'))
            {
                $newurl = esc_attr(trim($_POST['UmanITUpdateURLs_runquery_newurl']));
            }
            else // Use the deprecated version of esc_attr
            {
                $newurl = attribute_escape(trim($_POST['UmanITUpdateURLs_runquery_newurl']));
            }
            
            if ($newurl && $newurl != NEWURL_EXAMPLE && $newurl != '')
            {
            
                if ( !empty( $_POST['UmanITUpdateURLs_runquery_checkbox'] ) )
                {
                    // Ready to run the query
                    $result = $wpdb->query( $wpdb->prepare(("UPDATE wp_options SET option_value='$newurl' WHERE option_name='siteurl';")) );

                    if ($result>0)
                    {
        ?>
                        <div id="message" class="updateurls updated fade">
                            <p><strong><?php _e("Success", 'umanit-updateurls') ?></strong></p>
                            <p><?php echo sprintf(__("The ".'%1$s'."siteurl".'%2$s'." option has been set to ".'%3$s%4$s%5$s', 'umanit-updateurls'), '<code>', '</code>', '<code>', $newurl, '</code>'); ?></p>
                        </div>
        <?php
                    }
                    else
                    {
        ?>
                        <div id="message" class="updateurls error fade">
                            <p><strong><?php _e("Nothing to update", 'umanit-updateurls') ?></strong></p>
                            <p><?php echo sprintf(__("Maybe the ".'%1$s'."siteurl".'%2$s'." option was already set to ".'%3$s%4$s%5$s', 'umanit-updateurls'), '<code>', '</code>', '<code>', $newurl, '</code>'); ?></p>
                        </div>
        <?php
                    }
                }
                else
                {
        ?>
                    <div id="message" class="updateurls error fade">
                        <p><strong><?php _e("Error", 'umanit-updateurls'); ?> - <?php _e("The query was not executed", 'umanit-updateurls'); ?></strong></p>
                        <p><?php _e("You must check that you know what you're doing", 'umanit-updateurls'); ?></p>
                    </div>
        <?php
                }
            }
            else
            {
        ?>
                <div id="message" class="updateurls error fade">
                    <p><strong><?php _e("Error", 'umanit-updateurls'); ?> - <?php _e("The query was not executed", 'umanit-updateurls'); ?></strong></p>
                    <p><?php echo sprintf(__("You have to provide the new ".'%1$s'."siteurl".'%2$s', 'umanit-updateurls'), '<code>', '</code>'); ?></p>
                </div>
        <?php
            }
        }
        // Check that at least one form input is checked ***********************
        elseif(
               isset( $_POST['UmanITUpdateURLs_settings_submit'] ) &&
               !isset( $_POST['UmanITUpdateURLs_update_links'] )
              )
        {
        ?>
            <div id="message" class="updateurls error fade">
                <p><strong><?php _e("Error", 'umanit-updateurls'); ?> - <?php echo sprintf(__("Your ".'%1$s'."URLs".'%2$s'." have not been updated.", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></strong></p>
                <p><?php _e("Please select at least one checkbox.", 'umanit-updateurls'); ?></p>
            </div>
            <?php
        }
        // Try to update URLs **************************************************
        elseif( isset( $_POST['UmanITUpdateURLs_settings_submit'] ) )
        {

            if (
                ($oldurl && $oldurl != OLDURL_EXAMPLE && $oldurl != '') &&
                ($newurl && $newurl != NEWURL_EXAMPLE && $newurl != '')
            )
            {
                $urlsToUpdate = $_POST['UmanITUpdateURLs_update_links'];
                $results = updateURLs($urlsToUpdate, $oldurl, $newurl);
            ?>
                <div id="message" class="updateurls updated fade">
                        <?php
                            if (!empty($results)):
                        ?>
                                <p><strong><?php echo sprintf(__("Success! Your ".'%1$s'."URLs".'%2$s'." have been updated.", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></strong></p>
                                <table>
                                    <?php
                                        foreach ($results as $result)
                                        {
                                            echo '<tr><td class="count">'.$result['count'].'</td><td>'.$result['label'].'</td></tr>';
                                        }
                                    ?>
                                </table>
                    <?php
                            else:
                    ?>
                                <p><strong><?php echo sprintf(__("No ".'%1$s'."URL".'%2$s'." has been found.", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></strong></p>
                    <?php
                            endif;
                        ?>
                </div>
            <?php
            }
            else
            {
            ?>
                <div id="message" class="updateurls error fade">
                    <p><strong><?php _e("Error", 'umanit-updateurls'); ?> - <?php echo sprintf(__("Your ".'%1$s'."URLs".'%2$s'." have not been updated.", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></strong></p>
                    <p><?php echo sprintf(__("Please enter values for both the old ".'%1$s'."URL".'%2$s'." and the new ".'%1$s'."URL".'%2$s'.".", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></p>
                </div>
            <?php
            }
        }
    }
    ?>

    <div class="updateurls wrap">
        
        <div class="icon32" id="icon-tools"><br/></div>
        <h2><?php echo sprintf(__("Update ".'%1$s'."URLs".'%2$s', 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></h2>
        <p><?php echo sprintf(__("After moving a website, this plugin lets you fix old ".'%1$s'."URLs".'%2$s'." in content, excerpts, custom fields and options.", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></p>
        
        <h3><?php _e("Scope", 'umanit-updateurls'); ?></h3>
        <p><?php _e("Some extensions use custom database tables that are not from the Wordpress core. Since we can't adapt our code for each of them, it will not be possible for this plugin to manage them.", 'umanit-updateurls'); ?></p>

        <h3><?php _e("Prerequisites", 'umanit-updateurls'); ?></h3>
        <p><?php _e("Before moving the website, consider making a backup of the database, just in case. Then, once the site has been moved, you will need to execute this query on the database, to access the Wordpress administration panel:", 'umanit-updateurls'); ?></p>
        
        <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>" id="UmanITUpdateURLs_runquery_form">
            <pre class="code part1">UPDATE <?php echo $wpdb->options; ?> SET option_value='</pre>
            <input name="UmanITUpdateURLs_runquery_newurl" type="text" id="UmanITUpdateURLs_runquery_newurl" class="code" value="<?php echo (isset($newurl)) ? $newurl : NEWURL_EXAMPLE; ?>" />
            <pre class="code part2">' WHERE option_name='siteurl';</pre>
            
            <input name="UmanITUpdateURLs_runquery_submit" type="submit" id="UmanITUpdateURLs_runquery_submit" class="button-primary" title="<?php _e("If you continue, you'll be disconnect and won't be able to access the Wordpress administration panel", 'umanit-updateurls'); ?>" value="<?php _e("Run query", 'umanit-updateurls'); ?>" />
            <input name="UmanITUpdateURLs_runquery_checkbox" type="checkbox" id="UmanITUpdateURLs_runquery_checkbox" value="1" title="<?php _e("You need to tick the checkbox first", 'umanit-updateurls'); ?>" />
            <label for="UmanITUpdateURLs_runquery_checkbox">
                <?php _e("I know what i'm doing", 'umanit-updateurls'); ?>
            </label>
        </form>
        
        <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
            
            <h3><?php echo sprintf(__("Enter your ".'%1$s'."URLs".'%2$s'." in the fields below", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="UmanITUpdateURLs_oldurl">
                            <?php _e("Old site address", 'umanit-updateurls'); ?>
                        </label>
                    </th>
                    <td>
                        <input name="UmanITUpdateURLs_oldurl" type="text" id="UmanITUpdateURLs_oldurl" class="regular-text code" value="<?php echo (isset($oldurl) && $oldurl != '') ? $oldurl : OLDURL_EXAMPLE; ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="UmanITUpdateURLs_newurl">
                            <?php _e("New site address", 'umanit-updateurls'); ?>
                        </label>
                    </th>
                    <td>
                        <input name="UmanITUpdateURLs_newurl" type="text" id="UmanITUpdateURLs_newurl" class="regular-text code" value="<?php echo (isset($newurl) && $newurl != '') ? $newurl : NEWURL_EXAMPLE; ?>" />
                    </td>
                </tr>
            </table>
            
            <h3><?php echo sprintf(__("Choose which ".'%1$s'."URLs".'%2$s'." should be updated", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <?php echo sprintf(__('%1$s'."URLs".'%2$s'." to update", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span>
                                    <?php echo sprintf(__('%1$s'."URLs".'%2$s'." to update", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?>
                                </span>
                            </legend>
                            
                            <label for="select-all" class="select-all">
                                <input type="checkbox" id="select-all" />
                                <span><?php echo _e("Select all", 'umanit-updateurls'); ?></span>
                            </label>
                            <br class="select-all" />
                            
                            <label for="UmanITUpdateURLs_update_content_links">
                                <input name="UmanITUpdateURLs_update_links[]" type="checkbox" value="content" id="UmanITUpdateURLs_update_content_links"<?php if (in_array('content', $urlsToUpdate)) echo ' checked="checked"';?> />
                                <span><?php echo sprintf(__('%1$s'."URLs".'%2$s'." in page content", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></span>
                                <span class="description"><?php _e("posts, pages, custom post types, revisions", 'umanit-updateurls'); ?></span>
                            </label>
                            <br/>
                            
                            <label for="UmanITUpdateURLs_update_excerpts_links">
                                <input name="UmanITUpdateURLs_update_links[]" type="checkbox" value="excerpts" id="UmanITUpdateURLs_update_excerpts_links"<?php if (in_array('excerpts', $urlsToUpdate)) echo ' checked="checked"';?> />
                                <span><?php echo sprintf(__('%1$s'."URLs".'%2$s'." in excerpts", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></span>
                            </label>
                            <br/>
                            
                            <label for="UmanITUpdateURLs_update_attachments_links">
                                <input name="UmanITUpdateURLs_update_links[]" type="checkbox" value="attachments" id="UmanITUpdateURLs_update_attachments_links"<?php if (in_array('attachments', $urlsToUpdate)) echo ' checked="checked"';?> />
                                <span><?php echo sprintf(__('%1$s'."URLs".'%2$s'." for attachments", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></span>
                                <span class="description"><?php _e("images, documents and other media", 'umanit-updateurls'); ?></span>
                            </label>
                            <br/>
                            
                            <label for="UmanITUpdateURLs_update_custom_links">
                                <input name="UmanITUpdateURLs_update_links[]" type="checkbox" value="custom" id="UmanITUpdateURLs_update_custom_links"<?php if (in_array('custom', $urlsToUpdate)) echo ' checked="checked"';?> />
                                <span><?php echo sprintf(__('%1$s'."URLs".'%2$s'." in custom fields and meta boxes", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></span>
                            </label>
                            <br/>
                            
                            <label for="UmanITUpdateURLs_update_options_links">
                                <input name="UmanITUpdateURLs_update_links[]" type="checkbox" value="options" id="UmanITUpdateURLs_update_options_links"<?php if (in_array('options', $urlsToUpdate)) echo ' checked="checked"';?> />
                                <span><?php echo sprintf(__('%1$s'."URLs".'%2$s'." in global options", 'umanit-updateurls'), '<abbr title="Uniform Resource Locator" lang="en">', '</abbr>'); ?></span>
                            </label>
                            <br/>
                            
                            <label for="UmanITUpdateURLs_update_guids_links">
                                <input name="UmanITUpdateURLs_update_links[]" type="checkbox" value="guids" id="UmanITUpdateURLs_update_guids_links"<?php if (in_array('guids', $urlsToUpdate)) echo ' checked="checked"';?> />
                                <span><?php _e("Update all GUIDs", 'umanit-updateurls'); ?></span>
                                <span class="description"><?php _e("GUIDs for posts should only be changed on development sites.", 'umanit-updateurls'); ?></span>
                                <a href="http://codex.wordpress.org/Changing_The_Site_URL#Important_GUID_Note" target="_blank"><?php _e("Learn more", 'umanit-updateurls'); ?></a>.
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <?php
                // http://nooshu.com/wordpress-are-you-sure-you-want-to-do-this
                wp_nonce_field('UmanITUpdateURLs_submit', 'UmanITUpdateURLs_nonce');
            ?>
            <input name="UmanITUpdateURLs_settings_submit" type="submit" class="button-primary" value="<?php _e("Update URLs", 'umanit-updateurls'); ?>" />
        </form>
    </div>
    <?php
}
?>

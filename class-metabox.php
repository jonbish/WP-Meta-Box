<?php

class metaBoxes {
    static $scripts = array();
    static $scripts_ouput = array();
    
    static $styles = array();
    static $styles_output = array();
    
    public $meta_fields = array();
    public $meta_box_name = '';
     
    //private $admin_meta_boxes;
    private $post_type = '';
    private $page_template = '';
    private $box_context; // 'normal', 'advanced', or 'side'
    private $box_priority; //'high', 'core', 'default' or 'low'
    
    /**
     * Create a new instance of metaBoxes
     *
     * @access public
     *
     * @param string $post_type name of the post type to dislay meta
     */
    function metaBoxes($post_type = '', $page_template = '') {
        $this->post_type = $post_type;
        $this->page_template = $page_template;
    }

    /**
     * Adds a new metabox that fields can be added to
     *
     * @access public
     *
     * @param string $box_name The name of the meta box
     * @param string $box_context The part of the page where the edit screen section should be shown ('normal', 'advanced', or 'side') (optional)
     * @param string $box_priority The priority within the context where the boxes should show ('high', 'core', 'default' or 'low') (optional)
     */
    public function add_meta_box($box_name, $box_context = 'advanced', $box_priority = 'default') {
        $this->meta_box_name = $box_name;
        $this->box_context = $box_context; // 'normal', 'advanced', or 'side'
        $this->box_priority = $box_priority; //'high', 'core', 'default' or 'low'
    }

    /**
     * Adds new fields to meta boxes
     *
     * @access public
     *
     * @param string $box_name name of an existing meta box to add the field to
     * @param string $field_name the name of the meta box
     * @param string $field_the type of field to add (optional)
     * @param array $args any additional arguments (optional)
     */
    public function add_field($field_name, $field_type = 'input', $args = array()) {
        $field = array();

        $field['name'] = $field_name;
        $field['type'] = $field_type;
        
        $args = wp_parse_args( $args, array(
            'placement' => 'top',
            'value' => '1',
            'width' => '80%',
            'key' => sanitize_key($field['name']),
            'class' => ''
        ) );
         
        $field['args'] = $args;

        //array_push($this->admin_fields, $field);
        array_push($this->meta_fields, $field);
    }

    /**
     * Display meta boxes
     *
     * @access public
     *
     */
    public function display() {
        echo $this->generate_meta_box($this->meta_box_name);
    }

    /**
     * Display meta boxes
     *
     * @access public
     * 
     *@param int $post_ID the of the post you wish to process meta data for
     */
    public function process($post_ID) {
        $nonce_name = sanitize_key($this->meta_box_name) . '-nonce';
        
        if ( WP_DEBUG ) { dbgx_trace_var( $this->meta_fields, 'Saving Custom Meta' ); }
        
        if ( isset($_POST[$nonce_name]) && check_admin_referer('updating_amp_post_meta', $nonce_name) ){
            
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
                return $post_ID;

            if ('page' == $_POST['post_type']) {
                if (!current_user_can('edit_page', $post_ID))
                    return $post_ID;
            } else {
                if (!current_user_can('edit_post', $post_ID))
                    return $post_ID;
            }


            if (!wp_is_post_revision($post_ID) && !wp_is_post_autosave($post_ID)) {
                foreach($this->meta_fields as $field){
                    
                        do_action( 'amp_process_meta-' . $field['type'], $field );
                        
                        update_post_meta($post_ID, $field['args']['key'], $_POST[$field['args']['key']]);   
                        // Trace some variables if we're debugging
                        if ( WP_DEBUG ) { dbgx_trace_var( $_POST[$field['args']['key']], $field['args']['key'] ); }
                }
            }
        }
    }
    
    /**
     * Creates the new meta boxes
     *
     * @access public
     *
     */
    public function create_meta_box() {
        $post_id = $_GET['post'] ? $_GET['post'] : $_POST['post_ID'];
        // check for a template type
        $template_file = get_post_meta($post_id,'_wp_page_template',true);
        if ($this->page_template != ''){
            if ($template_file == $this->page_template){
                add_meta_box('amp-' . sanitize_key($this->meta_box_name), __($this->meta_box_name, 'amp'), array(&$this, 'display'), $this->post_type, $this->box_context, $this->box_priority);
            }
        } else {
            add_meta_box('amp-' . sanitize_key($this->meta_box_name), __($this->meta_box_name, 'amp'), array(&$this, 'display'), $this->post_type, $this->box_context, $this->box_priority);
        }
    }
    
    /**
     * Run all of the actions required to set up custom post types
     *
     * @access public
     *
     */
    public function init(){
        //$this->script_handler();
        add_action('admin_menu', array(&$this, 'create_meta_box'));
        add_action('save_post', array(&$this, 'process'));
        add_action('admin_head-post.php', array(&$this, 'scripts'));
        add_action('admin_head-post-new.php', array(&$this, 'scripts'));
        add_action('admin_head-post.php', array(&$this, 'styles'));
        add_action('admin_head-post-new.php', array(&$this, 'styles'));
    }
    
    /**
     * Add script to be displayed if a specific key (field) is in use
     *
     * @access public
     *
     * @param string $key the key of the field
     * @param string $script a string of javascript to insert into admin head
     * 
     */
    public function add_script($key, $js = ""){
        if($js != "" && !array_key_exists($key, self::$scripts)){
            //echo 'Adding js with a key of ' . $key . ' and js that is ' . strlen(serialize($js)) . ' bytes long';
            self::$scripts[$key] = $js;
        }
    }
    
    /**
     * Display all scripts
     *
     * @access public
     *
     */
    public function scripts(){
        //print_r(self::$scripts);
        if(!empty(self::$scripts)){
            foreach(self::$scripts as $key=>$js){
                foreach($this->meta_fields as $field){
                    //echo 'checking if ' . $key . ' = ' . $field['type'];
                    if($field['type'] == $key && !in_array($key, self::$scripts_ouput)){
                        echo '<script type="text/javascript">';
                        echo '
                            //' . $key;
                        echo $js;
                        echo '</script>';
                        array_push(self::$scripts_ouput, $key);
                    }
                }
            }
        }
    }
    
    /**
     * Add style to be displayed if a specific key (field) is in use
     *
     * @access public
     *
     * @param string $key the key of the field
     * @param string $script a string of javascript to insert into admin head
     * 
     */
    public function add_style($key, $css = ""){
        if($css != "" && !array_key_exists($key, self::$styles)){
            self::$styles[$key] = $css;
        }
    }
    
    /**
     * Display all styles
     *
     * @access public
     *
     */
    public function styles(){
        //print_r(self::$scripts);
        if(!empty(self::$styles)){
            foreach(self::$styles as $key=>$css){
                foreach($this->meta_fields as $field){
                    //echo 'checking if ' . $key . ' = ' . $field['type'];
                    if($field['type'] == $key && !in_array($key, self::$styles_ouput)){
                        echo '<style>';
                        echo '
                            //' . $key;
                        echo $css;
                        echo '</style>';
                        array_push(self::$styles_ouput, $key);
                    }
                }
            }
        }
    }
    
    /**
     * Create the HTML for a form field
     *
     * @access protected
     *
     * @param string $field_name the name of the meta box
     * @param string $field_the type of field to add (optional)
     * @param array $args any additional arguments (optional)
     * 
     * @return string $output_html a string of HTML with a field and label
     */
    protected function create_field($field_name, $field_type = 'text', $args = array()){
       
        
        return $output_html;
    }
    
     /**
     * Create the HTML for a form field
     *
     * @access protected
     *
     * @param string $box_name the name of the meta box
     * 
     * @return string $output_html a string of HTML containing all fields within specified box
     */
    protected function generate_meta_box($box_name){
       
        
        return $output_html;
    }
    
     /**
     * Runs on init and loads necessary scripts and styles in admin header
     *
     * @access protected
     *
     */
    protected function script_handler(){
        
    }
}

?>

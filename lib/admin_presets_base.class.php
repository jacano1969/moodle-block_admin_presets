<?php

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/blocks/admin_presets/lib/admin_presets_settings_types.php');


/**
 * Main admin_presets class
 *
 * Loads the common actions info and displays the header, the tabs
 * and the class (or child class) form
 *
 * @since      Moodle 2.0
 * @package    block/admin_presets
 * @copyright  2010 David Monllaó <david.monllao@urv.cat>
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt
 */
class admin_presets_base {


    protected $action;
    protected $mode;

    protected $adminroot;

    protected $outputs;
    protected $moodleform;

    protected $rel;


    /**
     * Loads common class attributes and initializes sensible settings and DB - XML relations
     */
    public function __construct() {

        $this->action = optional_param('action', 'base', PARAM_ALPHA);
        $this->mode = optional_param('mode', 'show', PARAM_ALPHAEXT);
        $this->id = optional_param('id', false, PARAM_INT);

        // DB - XML relations
        $this->rel = array('name' => 'NAME', 'comments' => 'COMMENTS',
                           'timecreated' => 'PRESET_DATE', 'site' => 'SITE_URL', 'author' => 'AUTHOR', 
                           'moodleversion' => 'MOODLE_VERSION', 'moodlerelease' => 'MOODLE_RELEASE');

        // Sensible settings
        $sensiblesettings = explode(',', str_replace(' ', '', get_config('admin_presets', 'sensiblesettings')));
        $this->sensiblesettings = array_combine($sensiblesettings, $sensiblesettings);
    }


    /**
     * Method to list the presets available on the system
     *
     * It allows users to access the different preset
     * actions (preview, load, download, delete and rollback)
     */
    public function show() {

        global $CFG, $DB, $OUTPUT;

        $presets = $DB->get_records('admin_preset');

        $this->outputs = '';

        if ($presets) {

            // Initialize table
            $table = $this->_create_preset_data_table();
            
            foreach ($presets as $preset) {

                // Preset actions
                $previewlink = $CFG->wwwroot.'/blocks/admin_presets/index.php?action=load&mode=preview&id='.$preset->id;
                $loadlink = $CFG->wwwroot.'/blocks/admin_presets/index.php?action=load&id='.$preset->id;
                $downloadlink = $CFG->wwwroot.'/blocks/admin_presets/index.php?action=export&mode=download_xml&sesskey='.sesskey().'&id='.$preset->id;
                $deletelink = $CFG->wwwroot.'/blocks/admin_presets/index.php?action=delete&id='.$preset->id;
                $rollbacklink = $CFG->wwwroot.'/blocks/admin_presets/index.php?action=rollback&id='.$preset->id;

                $actions = array();
                $actions[] = html_writer::link($previewlink, strtolower(get_string("preview")));
                $actions[] = html_writer::link($loadlink, get_string("load", "block_admin_presets"));
                $actions[] = html_writer::link($downloadlink, strtolower(get_string("download")));
                $actions[] = html_writer::link($deletelink, strtolower(get_string("delete")));

                // Look for preset applications
                if ($DB->get_records('admin_preset_apply', array('adminpresetid' => $preset->id))) {
                    $actions[] = html_writer::link($rollbacklink, get_string("rollback", "block_admin_presets"));
                }

                if ($preset->timeimported) {
                	$timeimportedstring = userdate($preset->timeimported);
                } else {
                	$timeimportedstring = '';
                }

                // Populate table
                $table->data[] = array(format_text($preset->name, FORMAT_PLAIN),
                    format_text($preset->comments, FORMAT_HTML),
                    format_text($preset->moodlerelease, FORMAT_PLAIN),
                    format_text($preset->author, FORMAT_PLAIN),
                    format_text(clean_text($preset->site, PARAM_URL), FORMAT_PLAIN),
                    userdate($preset->timecreated),
                    $timeimportedstring,
                    '<div>'.implode('</div><div>', $actions).'</div>');
            }

            $this->outputs .= html_writer::table($table);

        // If there aren't presets notify it
        } else {

            $exportlink = '<a href="'.$CFG->wwwroot.'/blocks/admin_presets/index.php?action=export">'.strtolower(get_string("actionexport", "block_admin_presets")).'</a>';
            $importlink = '<a href="'.$CFG->wwwroot.'/blocks/admin_presets/index.php?action=import">'.strtolower(get_string("actionimport", "block_admin_presets")).'</a>';

            $this->outputs = $OUTPUT->box_start('generalbox', 'id_nopresets');
            $this->outputs .= '<ul>'.get_string('nopresets', 'block_admin_presets');
            $this->outputs .= '<li>'.get_string('toexportclick', 'block_admin_presets', $exportlink).'</li>';
            $this->outputs .= '<li>'.get_string('toimportclick', 'block_admin_presets', $importlink).'</li>';
            $this->outputs .= '</ul>';
            $this->outputs .= $OUTPUT->box_end();

        }

    }


    /**
     * Main display method
     *
     * Prints the block header and the common block outputs, the
     * selected action outputs, his form and the footer
     *
     * $outputs value depends on $mode and $action selected
     */
    public function display() {

        global $CFG, $OUTPUT;

        $this->_display_header();

        // Other outputs
        if (!empty($this->outputs)) {
            echo $this->outputs;
        }

        // Form
        if ($this->moodleform) {
            $this->moodleform->display();
        }

        // Footer
        echo $OUTPUT->footer();
    }


    public function log() {

        global $SITE;

        if ($this->mode != 'show') {

            $action = $this->action;
            if ($this->mode != 'execute') {
                $action = $this->mode;
            }

            add_to_log($SITE->id, 'block_admin_presets', $action, '', $this->id);
        }
    }


    /**
     * Displays the header
     */
    protected function _display_header() {

        global $CFG, $PAGE, $OUTPUT, $SITE;

        // Strings
        $actionstr = get_string('action'.$this->action, 'block_admin_presets');
        $modestr = get_string($this->action.$this->mode, 'block_admin_presets');
        $titlestr = get_string('pluginname', 'block_admin_presets');

        // Header
        $PAGE->set_title($titlestr);
        $PAGE->set_heading($SITE->fullname);

        $PAGE->navbar->add(get_string('pluginname', 'block_admin_presets'),
            new moodle_url($CFG->wwwroot.'/blocks/admin_presets/index.php'));

        $PAGE->navbar->add($actionstr.': '.$modestr);

        echo $OUTPUT->header();

        include(dirname(dirname(__FILE__)).'/tabs.php');

        echo $OUTPUT->heading($actionstr.': '.$modestr, 1);
    }


    /**
     * Gets the system settings
     *
     * Loads the DB $CFG->prefix.'config' values and the
     * $CFG->prefix.'config_plugins' values and redirects
     * the flow through $this->_get_settings()
     *
     * @return    array        $settings        Array format $array['plugin']['settingname'] = admin_preset_setting child class
     */
    protected function _get_site_settings() {

        global $DB;

        // db configs (to avoid multiple queries)
        $dbconfig = $DB->get_records_select('config', '', array(), '', 'name, value');

        // adding site settings in course table
        $frontpagevalues = $DB->get_record_select('course', 'id = 1', array(), 'fullname, shortname, summary');
        foreach ($frontpagevalues as $field => $value) {
            $dbconfig[$field]->name = $field;
            $dbconfig[$field]->value = $value;
        }
        $sitedbsettings['none'] = $dbconfig;

        // Config plugins
        $configplugins = $DB->get_records('config_plugins');
        foreach ($configplugins as $configplugin) {
            $sitedbsettings[$configplugin->plugin][$configplugin->name]->name = $configplugin->name;
            $sitedbsettings[$configplugin->plugin][$configplugin->name]->value = $configplugin->value;
        }


        // Get an array with the common format
        $settings = $this->_get_settings($sitedbsettings, true, $settings = array());

        return $settings;
    }


    /**
     * Constructs an array with all the system settings
     *
     * If a setting value can't be found on the DB it considers
     * the default value as the setting value
     *
     * Settings without plugin are marked as 'none' in the plugin field
     *
     * Returns an standarized settings array format, $this->_get_settings_branches
     * will get the html or js to display the settings tree
     *
     * @param     array        $dbsettings         Standarized array, format $array['plugin']['name'] = obj('name'=>'settingname', 'value'=>'settingvalue')
     * @param     boolean      $sitedbvalues       Indicates if $dbsettings comes from the site db or not
     * @param     array        $settings           Array format $array['plugin']['settingname'] = admin_preset_setting child class
     * @param     array        $children           admin_category children
     * @return    array                            Array format $array['plugin']['settingname'] = admin_preset_setting child class
     */
    protected function _get_settings($dbsettings, $sitedbvalues = false, $settings, $children = false) {

        global $DB;

        // If there are no children, load admin tree and iterate through
        if (!$children) {
            $this->adminroot = & admin_get_root(false, true);
            $children = $this->adminroot->children;
        }

        // Iteates through children
        foreach ($children as $key => $child) {

            // We must search category children
            if (is_a($child, 'admin_category')) {

                if ($child->children) {
                    $settings = $this->_get_settings($dbsettings, $sitedbvalues, $settings, $child->children);
                }

            // Settings page
            } else if (is_a($child, 'admin_settingpage')) {

                if ($child->settings) {

                    foreach ($child->settings as $settingname => $values) {

                        unset($settingvalue);

                        // Look for his config value
                        if ($values->plugin == '') {
                            $values->plugin = 'none';
                        }

                        if (!empty($dbsettings[$values->plugin][$settingname])) {
                            $settingvalue = $dbsettings[$values->plugin][$settingname]->value;
                        }

                        // If no db value found default value
                        if ($sitedbvalues && !isset($settingvalue)) {

                            // For settings with multiple values
                            if (is_array($values->defaultsetting)) {

                                if (isset($values->defaultsetting['value'])) {
                                    $settingvalue = $values->defaultsetting['value'];

                                //  Configtime case, does not have a 'value' default setting...
                                } else {
                                    $settingvalue = 0;
                                }

                            } else {
                                $settingvalue = $values->defaultsetting;
                            }

                        }

                        // If there aren't any value loaded, skip that setting
                        if (!isset($settingvalue)) {
                            //debugging('Setting '.$settingname.' skipped because it doesn\'t have value');
                            continue;
                        }


                        // If there is no setting class defined continue
                        if (!$setting = $this->_get_setting($values, $settingvalue)) {

                            if (get_class($values) != 'admin_setting_heading') {
                                //debugging('admin_presets_'.get_class($values).' class must be implemented');
                            }
                            continue;
                        }

                        // admin_preset_setting childs with
                        // attributes provides an attributes array
                        if ($attributes = $setting->get_attributes()) {

                            // Look for settings attributes if it is a presets
                            if (!$sitedbvalues) {
                                $itemid = $dbsettings[$values->plugin][$settingname]->itemid;
                                $attrs = $DB->get_records('admin_preset_item_attr', array('itemid' => $itemid), '', 'name, value');
                            }
                            foreach ($attributes as $defaultvarname => $varname) {

                                unset($attributevalue);

                                // Settings from site
                                if ($sitedbvalues) {
                                    if (!empty($dbsettings[$values->plugin][$varname])) {
                                        $attributevalue = $dbsettings[$values->plugin][$varname]->value;
                                    }

                                // Settings from a preset
                                } else if (!$sitedbvalues && isset($attrs[$varname])) {
                                    $attributevalue = $attrs[$varname]->value;
                                }

                                // If no value found, default value
                                if (!isset($attributevalue)) {
                                    $attributevalue = $values->defaultsetting[$defaultvarname];
                                }

                                $setting->set_attribute_value($varname, $attributevalue);
                            }

                        }

                        // Setting the text
                        $setting->set_text();

                        // Adding to general settings array
                        $settings[$values->plugin][$settingname] = $setting;
                    }
                }

            }

        }

        return $settings;
    }


    /**
     * Gets the javascript to populate the settings tree
     *
     * @param    array   $settings      Array format $array['plugin']['settingname'] = admin_preset_setting child class
     */
    protected function _get_settings_branches($settings) {

    	global $PAGE;
    	
        // Nodes should be added in hierarchical order
        $nodes = array('categories' => array(), 'pages' => array(), 'settings' => array());
        $nodes = $this->_get_settings_elements($settings, false, false, $nodes);

        $PAGE->requires->js_init_call('M.block_admin_presets.init', null, true);
        
        $levels = array('categories', 'pages', 'settings'); 
        foreach ($levels as $level) {
	        foreach ($nodes[$level] as $data) {
	        	$ids[] = $data[0];
	        	$nodes[] = $data[1];
	        	$labels[] = $data[2];
	        	$descriptions[] = $data[3];
	        	$parents[] = $data[4];
	        }
        }
        $PAGE->requires->js_init_call('M.block_admin_presets.addNodes', array($ids, $nodes, $labels, $descriptions, $parents), true);
        
        $PAGE->requires->js_init_call('M.block_admin_presets.render', null, true);
    }


    /**
     * Gets the html code to select the settings to export/import/load
     *
     * @param     array     $allsettings        Array format $array['plugin']['settingname'] = admin_preset_setting child class
     * @param     object    $admintree          The admin tree branche object or false if we are in the root
     * @param     string    $jsparentnode       Name of the javascript parent category node
     * @param     array     $nodes              Tree nodes
     * @return    string                        Code to output
     */
    protected function _get_settings_elements($allsettings, $admintree = false, $jsparentnode = false, &$nodes) {

        global $CFG;

        if (empty($this->adminroot)) {
            $this->adminroot = & admin_get_root(false, true);
        }

        // If there are no children, load admin tree and iterate through
        if (!$admintree) {
            $this->adminroot = & admin_get_root(false, true);
            $admintree = $this->adminroot->children;
        }

        // If there are no parentnode specified the parent becomes the tree root
        if (!$jsparentnode) {
            $jsparentnode = 'root';
        }

        // Iterates through children
        foreach ($admintree as $key => $child) {

            $pagesettings = array();

            // We must search category children
            if (is_a($child, 'admin_category')) {

                if ($child->children) {

                    $categorynode = $child->name.'Node';
                    $nodes['categories'][$categorynode] = array("category", $categorynode, $child->visiblename, "", $jsparentnode);

                    // Not all admin_categories have admin_settingpages
                    $this->_get_settings_elements($allsettings, $child->children, $categorynode, $nodes);
                }

            // Settings page
            } else if (is_a($child, 'admin_settingpage')) {

                // Only if there are settings
                if ($child->settings) {

                    // The name of that page tree node
                    $pagenode = $child->name.'Node';

                    foreach ($child->settings as $settingname => $values) {


                        // IF no plugin was specified mark as 'none'
                        if (!$plugin = $values->plugin) {
                            $plugin = 'none';
                        }

                        if (empty($allsettings[$plugin][$settingname])) {
                            continue;
                        }

                        // Getting setting data
                        $setting = $allsettings[$plugin][$settingname];
                        $settingid = $setting->get_id();

                        // String to add the setting to js tree
                        $pagesettings[$settingid] = array($settingid, $settingid, $setting->get_text(), $setting->get_description(), $pagenode);
                    }

                    // The page node only should be added if it have children
                    if ($pagesettings) {
                        $nodes['pages'][$pagenode] = array("page", $pagenode, $child->visiblename, "", $jsparentnode);
                        $nodes['settings'] = array_merge($nodes['settings'], $pagesettings);
                    }

                }
            }

        }

        return $nodes;
    }


    /**
     * Returns the class type object
     *
     * @param     object                              $settingdata    Setting data
     * @param     mixed                               $currentvalue
     * @return    admin_preset_setting
     */
    protected function _get_setting($settingdata, $currentvalue) {

        global $CFG;

        // Getting the appropiate class to get the correct setting value value
        $settingtype = get_class($settingdata);

        // Skipping admin_*
        $classname = 'admin_preset_'.substr($settingtype, 6);

        // TODO: Implement all the settings types
        if (!class_exists($classname)) {
            return false;
        }

        $setting = new $classname($settingdata, $currentvalue);

        return $setting;
    }


    /**
     * Gets the standarized settings array from DB records
     *
     * @param    array       $dbsettings    Array of objects
     * @return   array                      Standarized array, format $array['plugin']['name'] = obj('name'=>'settingname', 'value'=>'settingvalue')
     */
    protected function _get_settings_from_db($dbsettings) {

        global $CFG;

        if (!$dbsettings) {
            return false;
        }

        $settings = array();
        foreach ($dbsettings as $dbsetting) {
            $settings[$dbsetting->plugin][$dbsetting->name]->itemid = $dbsetting->id;
            $settings[$dbsetting->plugin][$dbsetting->name]->name = $dbsetting->name;
            $settings[$dbsetting->plugin][$dbsetting->name]->value = $dbsetting->value;
        }

        return $settings;
    }


    protected function _output_applied_changes($appliedchanges) {

        $appliedtable = new html_table();
        $appliedtable->attributes['class'] = 'generaltable boxaligncenter';
        $appliedtable->head  = array(get_string('plugin'),
            get_string('settingname', 'block_admin_presets'),
            get_string('oldvalue', 'block_admin_presets'),
            get_string('newvalue', 'block_admin_presets'));

        $appliedtable->align = array('center', 'center');

        foreach ($appliedchanges as $setting) {
            $appliedtable->data[] = array($setting->plugin,
                $setting->visiblename,
                $setting->oldvisiblevalue,
                $setting->visiblevalue);
        }

        $this->outputs .= html_writer::table($appliedtable);
    }
    
    
    /**
     * Table to display preset/s data
     * 
     * @param boolean $actionstable If is set to true adds a column to display actions
     * @return html_table
     */
    protected function _create_preset_data_table($actionstable = true) {
     
        $table = new html_table();
        $table->attributes['class'] = 'generaltable boxaligncenter admin_presets_table';
        $table->align = array('left', 'left', 'center', 'left', 'left', 'center', 'center');
        $table->head  = array(get_string('name'), get_string('description'), get_string('presetmoodlerelease', 'block_admin_presets'),
            get_string('author', 'block_admin_presets'), get_string('site', 'block_admin_presets'), 
            get_string('created', 'block_admin_presets'), get_string('imported', 'block_admin_presets'));


        if ($actionstable) {
            $table->align[] = 'left';
            $table->head[] = get_string('actions');
            $table->size = array('14%', '16%', '12%', '11%', '17%', '10%', '10%', '10%');
        } else {
        	$table->size = array('17%', '20%', '13%', '12%', '18%', '10%', '10%');
        }
        
        return $table;
    }
    
    
    /**
     * Returns a table with the preset data
     * 
     * @param object $preset
     * @return string|string
     */
    protected function _html_writer_preset_info_table($preset) {

    	if (!$preset) {
    		return '';
    	}
    	
        if ($preset->timeimported) {
            $timeimportedstring = userdate($preset->timeimported);
        } else {
            $timeimportedstring = '';
        }
        $infotable = $this->_create_preset_data_table(false);
        $infotable->data[] = array(format_text($preset->name, FORMAT_PLAIN),
            format_text($preset->comments, FORMAT_HTML),
            format_text($preset->moodlerelease, FORMAT_PLAIN),
            format_text($preset->author, FORMAT_PLAIN),
            format_text(clean_text($preset->site, PARAM_URL), FORMAT_PLAIN),
            userdate($preset->timecreated),
            $timeimportedstring);

        return html_writer::table($infotable);
    }
}
<?php  

/*
=====================================================

RogEE "Nicer Wiki Titles"
an extension for ExpressionEngine 2
by Michael Rog
v1.0

email Michael with questions, feedback, suggestions, bugs, etc.
>> michael@michaelrog.com

Changelog:
0.1 - dev
1.0 - public release

=====================================================

*/

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// -----------------------------------------
//	Begin class
// -----------------------------------------

class Nicer_wiki_titles_ext
{

		var $settings		= array() ;
		var $nwt_tags		= array('title' => "{title}", 'nicer_title' => "{nicer_title}", 'rogee_nwt_title' => "{rogee_nwt_title}") ;
    	
		var $name			= "RogEE Nicer Wiki Titles" ;
		var $version		= "1.0.0" ;
		var $description	= "Allows wiki articles to have nicer 'display titles' with specific capitalization and punctuation." ;
		var $settings_exist	= "y" ;
		var $docs_url		= "http//michaelrog.com/go/ee" ;

		var $dev_on			= FALSE ;

	 	// ------------------------------
		// Settings
		// ------------------------------
		
		function settings()
		{
		
			$this->EE->lang->loadfile('nicer_wiki_titles');
			
			$settings = array();
			
			$settings['nwt_tag'] = array('s', $this->nwt_tags, 'nicer_title') ;
			$settings['nwt_substitute_url_title'] = array('r', array('y' => "yes", 'n' => "no"), 'y') ;
			$settings['nwt_fallback_text']  = "" ;
			
			return $settings;
			
		}



		// -------------------------------
		// Constructor 
		// -------------------------------                                                                                                                         
    
		function Nicer_wiki_titles_ext ($settings="")
		{
		
			// super object
			$this->EE =& get_instance();
			
			// settings
			$this->settings = (empty($settings)) ? $this->settings() : $settings ;
			
			// localize
			$this->EE->lang->loadfile('nicer_wiki_titles');
			$this->name = $this->EE->lang->line('nicer_wiki_titles_module_name') ;
			$this->description = $this->EE->lang->line('nicer_wiki_titles_module_description') ;
			
		} // END Constuctor



		// --------------------------------
		//  Activate Extension
		// --------------------------------

		function activate_extension()
		{

			// register hooks
			
			$this->EE->db->insert('exp_extensions',
				array(
				'extension_id' => '',
				'class'        => get_class($this),
				'method'       => "display_nice_title",
				'hook'         => "wiki_article_end",
				'settings'     => "",
				'priority'     => 4,
				'version'      => $this->version,
				'enabled'      => "y"
				)
			);
			
			$this->EE->db->insert('exp_extensions',
				array(
				'extension_id' => '',
				'class'        => get_class($this),
				'method'       => "store_nice_title",
				'hook'         => "edit_wiki_article_end",
				'settings'     => "",
				'priority'     => 2,
				'version'      => $this->version,
				'enabled'      => "y"
				)
			);			
			
			// create new column in exp_wiki_revisions to track article titles
	
			if ($this->EE->db->query('SHOW COLUMNS FROM exp_wiki_revisions LIKE "rogee_nwt_title"')->num_rows() == 0)
			{
				$this->EE->db->query('ALTER TABLE exp_wiki_revisions ADD COLUMN rogee_nwt_title TEXT DEFAULT NULL');
			}
			
		} // END activate_extension()


	
		// --------------------------------
		//  Store nice title when an article is saved
		// --------------------------------

		function store_nice_title($w_object, $w_query)
		{
		
			// If the article is being renamed, POST['rename'] is set. We can store it!
			// Else, we'll check to see if this article has a previously registered Nicer Title (from an 'open' revision):
			//  - If it does, we copy it as the Nicer Title for this new revision.
			//  - If not, we do nothing.
			
			$title_from_post = $this->EE->input->post('rename', TRUE);
			
			$new_revision_id = $this->EE->db->query("SELECT r.revision_id, r.rogee_nwt_title
								FROM exp_wiki_revisions r
								WHERE r.page_id = '".$w_query->row('page_id')."'
								AND r.wiki_id = '".$w_query->row('wiki_id')."'
								ORDER BY r.revision_date DESC LIMIT 1") -> row('revision_id') ;
			
			if ($title_from_post)
			{
			
				$data = array('rogee_nwt_title' => $title_from_post) ;
				$this->EE->db->query($this->EE->db->update_string('exp_wiki_revisions', $data, "revision_id = '$new_revision_id'")) ;
				
			} else {
				
				$last_valid_nice_title = $this->EE->db->query("SELECT r.revision_id, r.rogee_nwt_title
								FROM exp_wiki_revisions r
								WHERE r.page_id = '".$w_query->row('page_id')."'
								AND r.wiki_id = '".$w_query->row('wiki_id')."'
								AND r.revision_status = 'open'
								AND r.revision_id < $new_revision_id
								ORDER BY r.revision_date DESC LIMIT 1") -> row('rogee_nwt_title') ;
			
				if (!empty($last_valid_nice_title)) { 
		
					$data = array('rogee_nwt_title' => $last_valid_nice_title) ;
					$this->EE->db->query($this->EE->db->update_string('exp_wiki_revisions', $data, "revision_id = '$new_revision_id'")) ;
				
				}
			
			}
			
		} // END store_nice_title()



		// --------------------------------
		//  Find and display Nice Title when a basic article is processed
		// --------------------------------

		function display_nice_title ($w_object, $w_query)
		{

			// Look up the correct revision:
			//  - If EE gives us a wiki object with a revision ID, we'll use it.
			//  - Otherwise, we need to find the the last 'open' revision of this article.
			
			if (!empty($w_object->revision_id))
			{

			$revision_query = $this->EE->db->query("SELECT r.revision_id, r.rogee_nwt_title
								FROM exp_wiki_revisions r
								WHERE r.revision_id = '".$w_object->revision_id."'
								ORDER BY r.revision_date DESC LIMIT 1") ;			
			
			} else {
			
			$revision_query = $this->EE->db->query("SELECT r.revision_id, r.rogee_nwt_title
								FROM exp_wiki_revisions r
								WHERE r.page_id = '".$w_query->row('page_id')."'
								AND r.wiki_id = '".$w_query->row('wiki_id')."'
								AND r.revision_status = 'open'
								ORDER BY r.revision_date DESC LIMIT 1") ;
			
			}
			
			// Get some settings together...
			
			$tag = (is_array($this->settings['nwt_tag'])) ? $this->nwt_tags[$this->settings['nwt_tag'][2]] : $this->nwt_tags[$this->settings['nwt_tag']] ;			
			$fallback_to_title = (is_array($this->settings['nwt_substitute_url_title'])) ? $this->settings['nwt_substitute_url_title'][2] : $this->settings['nwt_substitute_url_title'] ;
			$fallback_text = $this->settings['nwt_fallback_text'] ;
						
			// If a Nicer Title is registered, do the replacement.
			// Else, figure out how to fall back, per settings.
			
			if ($revision_query->row('rogee_nwt_title') != NULL)

			{
				
				return str_replace($tag, $revision_query->row('rogee_nwt_title'), $w_object->return_data) ;

			} else {
				
				switch (true) {
				
					// If EE's {title} is our fallback AND the tag-to-replace is {title} we can be lazy and let the wiki module just do it's thing.
					case ($fallback_to_title == 'y' && $tag == "{title}"):
						return $w_object->return_data ;
						break;
					
					// Falling back to default article title, we replace the NWT tag for EE's {title} and let the module take it from there.
					case ($fallback_to_title == 'y' && $tag != "{title}"):
						return str_replace($tag, "{title}", $w_object->return_data) ;
						break;

					// Or, maybe the user wants to use his own text instead...
					case (!$fallback_to_title):
					default:
						return str_replace($tag, $fallback_text, $w_object->return_data) ;
						break;

				}
			
			}
			
		} // END display_nice_title()
		
		
		
		
		// --------------------------------
		//  Handle Nice Titles on special (non-article) pages
		// --------------------------------  

		function wiki_special_page ($w_object, $w_topic)
		{
					
			// coming, someday...

		} // END wiki_special_page()

		

		// --------------------------------
		//  Update Extension
		// --------------------------------  

		function update_extension ( $current='' )
		{

			if ($current == '' OR $current == $this->version)
			{
				return FALSE;		
			}
			
			if (version_compare($current, "1.0.0", "<"))
			{
				// Update to 1.0.0
			}

			$this->EE->db->query("UPDATE exp_extensions SET version = '".$DB->escape_str($this->version)."' WHERE class = '".get_class($this)."'");
			
		} // END update_extension()



		// --------------------------------
		//  Disable Extension
		// --------------------------------

		function disable_extension()
		{
		
			$this->EE->db->delete('exp_extensions', array('class' => get_class($this)));
			
		} // END disable_extension()


		
		// --------------------------------
		//  Debug (for development)
		// --------------------------------

		function debug($debug_statement = "")
		{
			if ($this->dev_on)
			{
				$this->EE->db->query('INSERT INTO rogee_debug_log (class, event, timestamp) VALUES ("'.get_class($this).'", "'.$debug_statement.'", CURRENT_TIMESTAMP)');
			}
			
			return $debug_statement ;
			
		} // END debug()
		
		
		
} // END CLASS

/* End of file ext.nicer_wiki_titles.php */
/* Location: ./system/expressionengine/third_party/nicer_wiki_titles/ext.nicer_wiki_titles.php */
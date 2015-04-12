<?php

class PerchBlog_Posts extends PerchAPI_Factory
{
    protected $table     = 'blog_posts';
	protected $pk        = 'postID';
	protected $singular_classname = 'PerchBlog_Post';
	
	protected $default_sort_column = 'postDateTime';
    protected $created_date_column = 'postDateTime';
	
	public $static_fields   = array('postTitle', 'postSlug', 'postDateTime', 'postDescRaw', 'postDescHTML', 'postTags', 'postStatus', 
                                        'authorID', 'authorGivenName', 'authorFamilyName', 'authorEmail', 'authorSlug');
	
    function __construct($api=false) 
    {
        $this->cache = array();
        parent::__construct($api);
    }
    
    public function all($Paging=false)
    {
        if ($Paging && $Paging->enabled()) {
            $sql = $Paging->select_sql();
        }else{
            $sql = 'SELECT';
        }
        
        $sql .= ' * 
                FROM ' . $this->table;
                
        if (isset($this->default_sort_column)) {
            $sql .= ' ORDER BY ' . $this->default_sort_column . ' DESC';
        }
        
        if ($Paging && $Paging->enabled()) {
            $sql .=  ' '.$Paging->limit_sql();
        }
        
        $results = $this->db->get_rows($sql);
        
        if ($Paging && $Paging->enabled()) {
            $Paging->set_total($this->db->get_count($Paging->total_count_sql()));
        }
        
        return $this->return_instances($results);
    }

    
    /*
        Get a single post by its ID
    */
    public function find($postID, $is_admin=false) 
    {
        $Cache = PerchBlog_Cache::fetch();
        
        if ($Cache->exists('p'.$postID)) {
            return $Cache->get('p'.$postID);
        }else{
            $sql = 'SELECT * FROM '.PERCH_DB_PREFIX.'blog_posts WHERE postID='.$this->db->pdb($postID);
            
            if (!$is_admin) {
                $sql .= ' AND postStatus=\'Published\' AND postDateTime<='.$this->db->pdb(date('Y-m-d H:i:00')).' ';
            }

            $row = $this->db->get_row($sql);

            if(is_array($row)) {
                $sql = 'SELECT categoryID FROM '.PERCH_DB_PREFIX.'blog_posts_to_categories WHERE postID = '.$this->db->pdb($postID);
                $result = $this->db->get_rows($sql);
                $a = array();
                if(is_array($result)) {
                    foreach($result as $cat_row) {
                        $a[] = $cat_row['categoryID'];
                    }
                }
                $row['cat_ids'] = $a;
            }

            $r = $this->return_instance($row);
            
            $Cache->set('p'.$postID, $r);
            
            return $r;
        }
        
        return false;
    }
    
    /*
        Get a single post by its Slug
    */
    public function find_by_slug($postSlug) 
    {
        $Cache = PerchBlog_Cache::fetch();
        
        if ($Cache->exists('p'.$postSlug)) {
            return $Cache->get('p'.$postSlug);
        }else{
            $sql = 'SELECT * FROM '.PERCH_DB_PREFIX.'blog_posts WHERE postStatus=\'Published\' AND postDateTime<='.$this->db->pdb(date('Y-m-d H:i:00')).' AND postSlug= '.$this->db->pdb($postSlug);
        
            $row = $this->db->get_row($sql);
        
            if(is_array($row)) {
                $sql = 'SELECT categoryID FROM '.PERCH_DB_PREFIX.'blog_posts_to_categories WHERE postID = '.$this->db->pdb($row['postID']);
                $result = $this->db->get_rows($sql);
                $a = array();
                if(is_array($result)) {
                    foreach($result as $cat_row) {
                        $a[] = $cat_row['categoryID'];
                    }
                }
                $row['cat_ids'] = $a;
            }
        
            $r = $this->return_instance($row);
            
            $Cache->set('p'.$postSlug, $r);
            
            return $r;
        }
        
        return false;
    }
    
    
	/**
	* takes the post data and inserts it as a new row in the database.
	*/
    public function create($data, $Template=false)
    {
        if(isset($data['postDescRaw'])) {
            if (is_object($Template)) {
                $data['postDescHTML'] = $this->text_to_html($data['postDescRaw'], $Template->find_tag('postDescHTML'));
            }else{
                $data['postDescHTML'] = $this->text_to_html($data['postDescRaw']);
            }
            
        }else{
            $data['postDescHTML'] = false;
        }
        
        if (isset($data['postTitle'])) {
            $data['postSlug'] = PerchUtil::urlify(date('Y m d', strtotime($data['postDateTime'])). ' ' . $data['postTitle']);
        }
        
        if (isset($data['cat_ids']) && is_array($data['cat_ids'])) {
            $cat_ids = $data['cat_ids'];
        }else{
            $cat_ids = false;
        }
        
        unset($data['cat_ids']);
        
        $postID = $this->db->insert($this->table, $data);
       
		if ($postID) {
			if(is_array($cat_ids)) {
				for($i=0; $i<sizeOf($cat_ids); $i++) {
				    $tmp = array();
				    $tmp['postID'] = $postID;
				    $tmp['categoryID'] = $cat_ids[$i];
				    $this->db->insert(PERCH_DB_PREFIX.'blog_posts_to_categories', $tmp);
				}
			}
			
			// Split tag string into array
			if($data['postTags'] != '') {
				$a = explode(',',$data['postTags']);
				if (is_array($a)) {
 					for($i=0; $i<sizeOf($a); $i++) {
						$tmp = array();
						$tmp['postID'] = $postID;
					
						$tag_str = trim($a[$i]);
					//does this tag exist
					$sql = 'SELECT tagID, tagTitle FROM '.PERCH_DB_PREFIX.'blog_tags WHERE tagTitle = '.$this->db->pdb($tag_str).' LIMIT 1';
					
					$row = $this->db->get_row($sql);
					
					
					if(is_array($row)) {
						$tmp['tagID'] = $row['tagID'];
					}else{
						$tag = array();
						$tag['tagTitle'] = $tag_str;
						$tag['tagSlug'] = PerchUtil::urlify($tag_str);
						$tmp['tagID'] = $this->db->insert(PERCH_DB_PREFIX.'blog_tags', $tag);
					}

 			    	
 			    		$this->db->insert(PERCH_DB_PREFIX.'blog_posts_to_tags', $tmp);
 					}
 				}
		
		
		
			}
			
            return $this->find($postID, true);
		}				
        return false;
	}
	
	
 
    public function get_display($type='latest', $month, $year, $opts)
    {
        // options
        $categories = false;
        $templates = array();
        
        if (is_array($opts)) {
            
            // categories
            if (isset($opts['category'])) {
                if (is_array($opts['category'])) {
                    $categories = $opts['category'];
                }else{
                    $categories = array($opts['category']);
                }
            }
            
            
            
            // templates
            
            if (isset($opts['blog_post-template'])) {
                $templates['blog-post'] = $opts['blog-post-template'];
            }
            
            // dates
            if (isset($opts['month'])) {
                $month = $opts['month'];
            }
            
            if (isset($opts['year'])) {
                $year = $opts['year'];
            }
        }
        
        $posts = $this->get_latest($count=false);
        
        switch($type) {
        	default:
                $DisplayListing = new PerchBlog_DisplayListing($this->api, $year, $month);
                $DisplayListing->set_posts($posts);

            	$r = $DisplayListing->display($templates);
                break;
                
            
        }
        
    	
    	return $r;
    }
    
    public function get_latest($count) {
    	$sql = 'SELECT p.*
                FROM '.$this->table.' p
                WHERE postStatus=\'Published\'
                ORDER BY '.$this->default_sort_column .' DESC';
    	
    	if($count) {
    		$sql.= ' LIMIT '. $count;
    	}

        $rows   = $this->db->get_rows($sql);
        

        return $this->return_instances($rows);
    }
    
    public function get_custom($opts)
    {
        $posts = array();
        $Post = false;
        $single_mode = false;
        $where = array();
        $order = array();
        $limit = '';
        
        
        // find specific _id
	    if (isset($opts['_id'])) {
	        $single_mode = true;
	        $Post = $this->find($opts['_id']);
	    }else{        
	        // if not picking an _id, check for a filter
	        if (isset($opts['filter']) && isset($opts['value'])) {
	            
	            
	            $key = $opts['filter'];
	            $raw_value = $opts['value'];
	            $value = $this->db->pdb($opts['value']);
	            
	            $match = isset($opts['match']) ? $opts['match'] : 'eq';
                switch ($match) {
                    case 'eq': 
                    case 'is': 
                    case 'exact': 
                        $where[] = $key.'='.$value;
                        break;
                    case 'neq': 
                    case 'ne': 
                    case 'not': 
                        $where[] = $key.'!='.$value;
                        break;
                    case 'gt':
                        $where[] = $key.'>'.$value;
                        break;
                    case 'gte':
                        $where[] = $key.'>='.$value;
                        break;
                    case 'lt':
                        $where[] = $key.'<'.$value;
                        break;
                    case 'lte':
                        $where[] = $key.'<='.$value;
                        break;
                    case 'contains':
                        $v = str_replace('/', '\/', $raw_value);
                        $where[] = $key." REGEXP '/\b".$v."\b/i'";
                        break;
                    case 'regex':
                    case 'regexp':
                        $v = str_replace('/', '\/', $raw_value);
                        $where[] = $key." REGEXP '".$v."'";
                        break;
                    case 'between':
                    case 'betwixt':
                        $vals  = explode(',', $raw_value);
                        if (PerchUtil::count($vals)==2) {
                            $where[] = $key.'>'.trim($this->db->pdb($vals[0]));
                            $where[] = $key.'<'.trim($this->db->pdb($vals[1]));
                        }
                        break;
                    case 'eqbetween':
                    case 'eqbetwixt':
                        $vals  = explode(',', $raw_value);
                        if (PerchUtil::count($vals)==2) {
                            $where[] = $key.'>='.trim($this->db->pdb($vals[0]));
                            $where[] = $key.'<='.trim($this->db->pdb($vals[1]));
                        }
                        break;
                    case 'in':
                    case 'within':
                        $vals  = explode(',', $raw_value);
                        if (PerchUtil::count($vals)) {
                            $where[] = $key.' IN ('.$this->implode_for_sql_in($vals).') ';                            
                        }
                        break;
                }
	        }
	    }
    
	    // sort
	    if (isset($opts['sort'])) {
	        $desc = false;
	        if (isset($opts['sort-order']) && $opts['sort-order']=='DESC') {
	            $desc = true;
	        }else{
	            $desc = false;
	        }
	        $order[] = $opts['sort'].' '.($desc ? 'DESC' : 'ASC');
	    }
    
	    if (isset($opts['sort-order']) && $opts['sort-order']=='RAND') {
            $order[] = 'RAND()';
        }
    
	    // limit
	    if (isset($opts['count'])) {
	        $count = (int) $opts['count'];
        
	        if (isset($opts['start'])) {
                $start = (((int) $opts['start'])-1). ',';
	        }else{
	            $start = '';
	        }
        
	        $limit = $start.$count;
	    }
	    
	    if ($single_mode){
	        $posts = array($Post);
	    }else{
	        
	        // Paging
	        $Paging = $this->api->get('Paging');
	        if ((!isset($count) || !$count) || (isset($opts['start']) && $opts['start']!='')) {
	            $Paging->disable();
	        }else{
	            $Paging->set_per_page($count);
	            if (isset($opts['start']) && $opts['start']!='') {
	                PerchUtil::debug('setting start pos: '.$opts['start']);
	                $Paging->set_start_position($opts['start']);
	            }
	        }
	        
    	    $sql = $Paging->select_sql() . ' p.* FROM '.$this->table.' p ';
	    
            // categories
            if (isset($opts['category'])) {
                $cats = $opts['category'];
                if (!is_array($cats)) $cats = array($cats);
        
                if (is_array($cats)) {
                    $sql = $Paging->select_sql() . ' p.*
                            FROM '.$this->table.' p, '.PERCH_DB_PREFIX.'blog_posts_to_categories p2c, '.PERCH_DB_PREFIX.'blog_categories c ';
                    $where[] =  'p.postID=p2c.postID AND p2c.categoryID=c.categoryID AND categorySlug IN ('.$this->implode_for_sql_in($cats).') ';
                }
            }
            
            // tags
            if (isset($opts['tag'])) {
                $tags = $opts['tag'];
                if (!is_array($tags)) $tags = array($tags);
        
                if (is_array($tags)) {
                    $sql = $Paging->select_sql() . ' p.*
                            FROM '.$this->table.' p, '.PERCH_DB_PREFIX.'blog_posts_to_tags p2t, '.PERCH_DB_PREFIX.'blog_tags t ';
                    $where[] =  'p.postID=p2t.postID AND p2t.tagID=t.tagID AND tagSlug IN ('.$this->implode_for_sql_in($tags).') ';
                }
            }
	    	
	    	$sql .= ' WHERE  postStatus=\'Published\' AND postDateTime<='.$this->db->pdb(date('Y-m-d H:i:00')).' ';            
	    	
    	    if (count($where)) {
    	        $sql .= ' AND ' . implode(' AND ', $where);
    	    }
	    
    	    if (count($order)) {
    	        $sql .= ' ORDER BY '.implode(', ', $order);
    	    }
	        
	        if ($Paging->enabled()) {
	            $sql .= ' '.$Paging->limit_sql();
	        }else{
	            if ($limit!='') {
        	        $sql .= ' LIMIT '.$limit;
        	    }
	        }
	        	    	    
    	    $rows    = $this->db->get_rows($sql);
    	    
    	    if ($Paging->enabled()) {
    	        $Paging->set_total($this->db->get_count($Paging->total_count_sql()));
    	    }
    	        	    
    	    $posts  = $this->return_instances($rows);

        }
	    
	    
        if (isset($opts['skip-template']) && $opts['skip-template']==true) {
            
            if ($single_mode) return $Post;
            
            $out = array();
            if (PerchUtil::count($posts)) {
                foreach($posts as $Post) {
                    $out[] = $Post->to_array();
                }
            }
            return $out; 
	    }
	    
	    // template
	    if (isset($opts['template'])) {
	        $template = $opts['template'];
	    }else{
	        $template = 'blog/post.html';
	    }
	    
	    if (isset($Paging) && $Paging->enabled()) {
            $paging_array = $Paging->to_array();
            // merge in paging vars
    	    if (PerchUtil::count($posts)) {
    	        foreach($posts as &$Post) {
    	            foreach($paging_array as $key=>$val) {
    	                $Post->squirrel($key, $val);
    	            }
    	        }
    	    }
        }
        	    
	    $Template = $this->api->get("Template");
	    $Template->set($template, 'blog');
	    
        $html = $Template->render_group($posts, true);

	    return $html;
    }
    
    /**
     * gets the listing by category
     * @param varchar $slug
     */
    public function get_by_category_slug($slug)
    {
        $sql = 'SELECT p.*
                FROM '.$this->table.' p, '.PERCH_DB_PREFIX.'blog_categories c, '.PERCH_DB_PREFIX.'blog_posts_to_categories p2c
                WHERE p.postID=p2c.postID AND p2c.categoryID=c.categoryID
                    AND c.categorySlug='.$this->db->pdb($slug).'
                    AND p.postStatus=\'Published\'
                    AND p.postDateTime<='.$this->db->pdb(date('Y-m-d H:i:00')).' 
                ORDER BY '.$this->default_sort_column.' DESC';

        $rows   = $this->db->get_rows($sql);

        return $this->return_instances($rows);
    }
 
    public function get_by_category_slug_for_admin_listing($slug)
    {
        $sql = 'SELECT p.*
                FROM '.$this->table.' p, '.PERCH_DB_PREFIX.'blog_categories c, '.PERCH_DB_PREFIX.'blog_posts_to_categories p2c
                WHERE p.postID=p2c.postID AND p2c.categoryID=c.categoryID
                    AND c.categorySlug='.$this->db->pdb($slug).'
                ORDER BY '.$this->default_sort_column.' DESC';

        $rows   = $this->db->get_rows($sql);

        return $this->return_instances($rows);
    }


    public function get_by_status($status='PUBLISHED')
    {
        switch(strtoupper($status)) {
            case 'PUBLISHED':
                $status = 'Published';
                break;

            default:
                $status = 'Draft';
                break;

        }

        $sql = 'SELECT p.*
                FROM '.$this->table.' p
                WHERE p.postStatus='.$this->db->pdb($status).'
                ORDER BY '.$this->default_sort_column.' DESC';

        $rows   = $this->db->get_rows($sql);

        return $this->return_instances($rows);
    }




    private function implode_for_sql_in($rows)
    {
        foreach($rows as &$item) {
            $item = $this->db->pdb($item);
        }
        
        return implode(', ', $rows);
    }
    
    public function get_years() 
    {
        $Cache = PerchBlog_Cache::fetch();
        
        if ($Cache->exists('get_years')) {
            return $Cache->get('get_years');
        }
        
 	    $sql = 'SELECT year(postDateTime) as year, COUNT(*) AS year_qty
    	        FROM '.$this->table .' 
    	        WHERE postStatus=\'Published\'
                    AND postDateTime<='.$this->db->pdb(date('Y-m-d H:i:00')).'
    	        GROUP BY year
    	        ORDER BY year DESC';
    	
    	$rows   = $this->db->get_rows($sql);

        $Cache->set('get_years', $rows);
        
    	return $rows;
    	
    }
    
    public function get_months_for_year($year) 
    {
        
        $Cache = PerchBlog_Cache::fetch();
        
        if ($Cache->exists('months_for_year_'.$year)) {
            return $Cache->get('months_for_year_'.$year);
        }

    	$sql = 'SELECT DISTINCT 
    	            year(postDateTime) AS year,
    	            month(postDateTime) AS month,
    	            CONCAT(year(postDateTime),"-",month(postDateTime),"-01") AS postDateTime,
    	            COUNT(*) AS month_qty
                FROM '.$this->table .' p
            	WHERE year(postDateTime) = '.$this->db->pdb($year).' 
            	    AND p.postStatus=\'Published\'
                    AND p.postDateTime<='.$this->db->pdb(date('Y-m-d H:i:00')).'
            	GROUP BY year, month
            	ORDER BY month DESC';
            	
        $rows   = $this->db->get_rows($sql);
    	
    	$Cache->set('months_for_year_'.$year, $rows);
    	
    	return $rows;
    }
    

    public function text_to_html($str, $tag=false)
    {
        if (is_object($tag)) {
            $formatting_language_used = false;

            // Textile
            if (!$formatting_language_used && PerchUtil::bool_val($tag->textile()) == true) {
                $Textile = new Textile;
                $str  =  $Textile->TextileThis($str);
                $formatting_language_used = true;
            }

            // Markdown
            if (!$formatting_language_used && PerchUtil::bool_val($tag->markdown()) == true) {
                $Markdown = new Markdown_Parser;
                $str = $Markdown->transform($str);
                $formatting_language_used = true;
            }
            
            
            
        }else{
            switch(PERCH_APPS_EDITOR_MARKUP_LANGUAGE) {
                case 'textile' :
                    $Textile = new Textile;
                    $str  =  $Textile->TextileThis($str);
                    break;

                case 'markdown' :
                    $Markdown = new Markdown_Parser;
                    $str = $Markdown->transform($str);
                    break;
            }
        }
        

        
        if (defined('PERCH_XHTML_MARKUP') && PERCH_XHTML_MARKUP==false) {
		    $str = str_replace(' />', '>', $str);
		}
		
		return $str;
    }
    
}

?>

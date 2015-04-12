<?php

class PerchBlog_Tags extends PerchAPI_Factory
{
    protected $table     = 'blog_tags';
	protected $pk        = 'tagID';
	protected $singular_classname = 'PerchBlog_Tag';
	
	protected $default_sort_column = 'tagTitle';
	
	/**
	 * fetch the posts for a given tag
	 * @param int $postID
	 */
	public function get_for_post($postID)
	{
	    $sql = 'SELECT t.*
	            FROM '.$this->table.' t, '.PERCH_DB_PREFIX.'blog_posts_to_tags p2t
	            WHERE t.tagID=p2t.tagID
	                AND p2t.postID='.$this->db->pdb($postID);
	    $rows   = $this->db->get_rows($sql);
	    
	    return $this->return_instances($rows);
	}
	
/**
	 * 
	 * retrieves all tags used by blog posts along with a count of number of posts for each tag.
	 */
	public function all_in_use() {
		$sql = 'SELECT t.tagTitle, t.tagSlug, COUNT(p2t.postID) AS qty
                FROM '.PERCH_DB_PREFIX.'blog_tags t, '.PERCH_DB_PREFIX.'blog_posts_to_tags p2t, '.PERCH_DB_PREFIX.'blog_posts p
                WHERE p2t.tagID=t.tagID AND p2t.postID=p.postID
                    AND p.postStatus=\'Published\' AND p.postDateTime<='.$this->db->pdb(date('Y-m-d H:i:00')).' 
                GROUP BY t.tagID
                ORDER BY t.tagTitle ASC
		';
		
		$rows   = $this->db->get_rows($sql);

    	$r = $this->return_instances($rows);
    	    
    	return $r;
	}
    
}

?>
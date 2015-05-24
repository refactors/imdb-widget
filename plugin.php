<?php

/**
 * Plugin Name: IMDb Widget
 * Description: This is a plugin that shows your IMDd profile with a simple widget.
 * Version: 1.0.0
 * Author: Henrique Dias and Luís Soares (Unknown Exceptions)
 * Author URI: https://github.com/unknown-exceptions
 * Network: true
 * License: GPL2 or later
 */
// third-party libraries
require_once( 'lib/htmlcompressor.php' );
require_once( 'vendor/autoload.php' );

use SmartScrapper\Parser;

// prevent direct file access
if (!defined('ABSPATH'))
{
    exit;
}

class IMDb_Widget extends WP_Widget
{

    protected $widget_slug = 'imdb-widget';
    private $options = array(
        "title",
        "userId"
    );

    public function __construct()
    {
        parent::__construct(
                $this->get_widget_slug(), __('IMDb Widget', $this->get_widget_slug()), array(
            'classname' => $this->get_widget_slug() . '-class',
            'description' => __('A widget to show a small version of your IMDb profile.', $this->get_widget_slug())
                )
        );

        add_action('wp_enqueue_scripts', array($this, 'register_widget_styles'));
        add_action('wp_enqueue_scripts', array($this, 'register_widget_scripts'));
    }

    public function get_widget_slug()
    {
        return $this->widget_slug;
    }

    public function form($config)
    {
        $config = !empty($config) ? unserialize($config) : array();

        foreach ($this->options as $option)
        {
            ${$option} = isset($config[$option]) ? $config[$option] : null;
        }

        ob_start("HTMLCompressor");
        require 'pieces/options.php';
        ob_end_flush();
    }

    public function update($new_instance, $old_instance)
    {
        return serialize($new_instance);
    }

    public function widget($args, $instance)
    {
        extract($args, EXTR_SKIP);
        $instance = !empty($instance) ? unserialize($instance) : array();

        ob_start("HTMLCompressor");

        if (!isset($instance['userId']))
        {
            echo 'You need to first configure the plugin :)';
        } else
        {
            $info = $this->get_info($instance['userId']);
            require 'pieces/widget.php';
        }
        ob_end_flush();
    }

    private function get_info($userId)
    {
        $info = new Parser('http://www.imdb.com/' . 'user/' . $userId . '/');
        $info->baseUrl = 'http://www.imdb.com';

        foreach (
        array('ratings', 'boards', 'lists', 'watchlist', 'checkins', 'boards/sendpm', 'comments-index', '#pollResponses') as $relativeUrl)
        {
            $cleanId = preg_replace('/[^A-Za-z]/', '', $relativeUrl);
            $info->{$cleanId . 'Url'} = $info->url . $relativeUrl;
        }

        $info->saveText('nick', '.header h1');
        $info->saveText('avatar', '#avatar-frame img', 'src');
        $info->saveText('memberSince', '.header .timestamp');
        $info->saveText('bio', '.header .biography');
        $info->saveText('ratingsCount', '.see-more a');
        $info->saveHtml('ratingsDistribution', '.overall .histogram-horizontal');
        $info->saveHtml('ratingsByYear', '.byYear .histogram-horizontal');
        $info->saveHtml('ratingsByYearLegend', '.byYear .legend');
        $info->saveHtml('ratingsTopRatedGenres', '.histogram-vertical', 0);
		$info->saveHtml('ratingsTopRatedYears', '.histogram-vertical' , 1 );

        $info->at('.ratings .item')
                ->with('link', 'a', 'href')
                ->with('logo', 'a img', 'src')
                ->with('title', '.title a')
                ->with('rating', '.sub-item .only-rating')
                ->saveList('ratings');

        $info->at('.badges .badge-frame')
                ->with('title', '.name')
                ->with('value', '.value')
                ->saveList('badges');

        $info->at('.watchlist .item')
                ->with('title', '.sub-item a')
                ->with('link', 'a', 'href')
                ->with('logo', 'a img', 'src')
                ->saveList('watchlist');

        $info->at('.lists .user-list')
                ->with('title', '.list-name')
                ->with('link', '.list-meta', 'href')
                ->with('meta', '.list-meta')
                ->saveList('lists');

        $info->at( '.user-lists .user-list')
                ->with('logo', 'img', 'src')
                ->with('title', '.list-name')
                ->with('link', 'a', 'href')
                ->with('description', '.list-meta')
                ->saveList('userLists');

        $info->at( '.reviews .item')
                ->with('movieLogo', '.image img', 'src')
                ->with('movieLink', '.image a', 'href')
                ->with('movieTitle', 'h3 a')
                ->with('movieYear', 'h3 span')
                ->with('title', 'h4')
                ->with('meta', '.details')
                ->with('text', 'p')
                ->saveList('reviews');
	
		$info->at( '.boards-comments .row' )
                ->with('boardLink', '.board a', 'href')
				->with('boardTitle', '.board a')
                ->with('commentTitle', '.title a')
                ->with('commentLink', '.title a' , 'href')
                ->with('when', '.when')
                ->saveList('boards');
		
        return $info;
    }

    public function register_widget_styles()
    {
        wp_enqueue_style($this->get_widget_slug() . '-widget-styles', plugins_url('css/widget.css', __FILE__));
    }

    public function register_widget_scripts()
    {
        wp_enqueue_script($this->get_widget_slug() . '-script', plugins_url('js/widget.js', __FILE__), array('jquery'));
    }

}

add_action('widgets_init', create_function('', 'return register_widget("IMDb_Widget");'));
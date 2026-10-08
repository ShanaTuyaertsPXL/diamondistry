<?php
/**
 * Diamondistry theme functions.
 *
 * @package Diamondistry
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme setup.
 */
function diamondistry_theme_setup() {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
}

add_action( 'after_setup_theme', 'diamondistry_theme_setup' );

/**
 * Load the main theme stylesheet.
 */
function diamondistry_theme_enqueue_styles() {
	wp_enqueue_style(
		'diamondistry-theme',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}

add_action( 'wp_enqueue_scripts', 'diamondistry_theme_enqueue_styles' );
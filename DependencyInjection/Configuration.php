<?php

namespace Verint\FeedbackBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $node = $treeBuilder->root('verint');
        
        $node->children()
        ->scalarNode( 'wdslurl' )->isRequired()->cannotBeEmpty()->end()
        ->scalarNode( 'userid')->isRequired()->cannotBeEmpty()->end()
        ->scalarNode( 'password' )->isRequired()->cannotBeEmpty()->end();
        return $treeBuilder;
    }
}

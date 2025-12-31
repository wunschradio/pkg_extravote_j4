<?php
/*------------------------------------------------------------------------
# plg_extravote - ExtraVote Plugin
# ------------------------------------------------------------------------
# author    Conseilgouz
# from joomlahill Plugin
# Copyright (C) 2024 www.conseilgouz.com. All Rights Reserved.
# @license - https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
-------------------------------------------------------------------------*/

namespace ConseilGouz\Plugin\Content\Extravote\Extension;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

define("EXTRAVOTE_OPTION_AFTER_TITLE", 0);
define("EXTRAVOTE_OPTION_AFTER_CONTENT", 1);
define("EXTRAVOTE_OPTION_HIDE", 2);


class Extravote extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    protected $article_id;
    protected $article_title;
    protected $view;
    public $myname = 'Extravote';
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentBeforeDisplay'   => 'checkExtra',
            'onSchemaBeforeCompileHead' => 'beforeCompileHead'
        ];
    }
    public function checkExtra($event)
    {
        if (strpos($event->getContext(), 'com_content') !== false) {
            $input               = Factory::getApplication()->input;
            $this->view          = $input->getCmd('view');
            $this->article_id    = $event->getItem()->id;
            $this->article_title = $event->getItem()->title;

            $this->ExtraVotePrepare($event->getItem(), $event->getParams());

            if ($this->params->get('display') == EXTRAVOTE_OPTION_AFTER_TITLE) {
                $hide  = $this->params->get('hide', 1);

                if ($hide != 1 || $this->view == 'article') {
                    $event->getItem()->xid = 0;
                    $event->addResult($this->ContentExtraVote($event->getItem(), $event->getParams()));
                }
            }
        }
    }
    protected function ContentExtraVote($article, $params)
    {
        $table = ($this->params->get('table', 1) == 1 ? '#__content_extravote' : '#__content_rating');
        $rating_count = $rating_sum = 0;
        $html = $ip = '';

        if ($params->get('show_vote')) {
            $db	= $this->getDatabase();
            $query = 'SELECT * FROM ' . $table . ' WHERE content_id='.$this->article_id . ($table == '#__content_extravote' ? ' AND extra_id = 0' : '');
            $db->setQuery($query);
            $vote = $db->loadObject();
            if($vote) {
                $rating_sum   = $vote->rating_sum;
                $rating_count = intval($vote->rating_count);
                $ip = $vote->lastip;
            }
            $html .= $this->plgContentExtraVoteStars($this->article_id, $rating_sum, $rating_count, $article->xid, $ip);
        }
        return $html;
    }
    protected function plgContentExtraVoteStars($id, $rating_sum, $rating_count, $xid, $ip)
    {
        $plg = 'media/plg_content_extravote/';

        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        if ($this->params->get('css', 1)) :
            $wa->registerAndUseStyle('extravotecontent', $plg.'extravote.css');
        endif;
        $wa->registerAndUseScript('extravotecontent', $plg.'extravote.js');
        if ($this->params->get('customcss')) {
            $wa->addInlineStyle($this->params->get('customcss'));
        }

        global $plgContentExtraVoteAddScript;

        $show_counter = $this->params->get('show_counter', 1);
        $show_rating  = $this->params->get('show_rating', 1);
        $rating_mode  = $this->params->get('rating_mode', 1);
        $show_unrated = $this->params->get('show_unrated', 1);
        $initial_hide = $this->params->get('initial_hide', 0);
        $currip = $_SERVER['REMOTE_ADDR'];
        $add_snippets = 0;
        if (PluginHelper::isEnabled('system', 'schemaorg')) {
            $add_snippets = 0; // will be added by onSchemaBeforeCompileHead event
        }
        $rating  = 0;

        if(!$plgContentExtraVoteAddScript) {
            $wa->addInlineScript("
                var ev_basefolder = '".URI::base(true)."';
                var extravote_text=Array('".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_NO_AJAX')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_LOADING')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_THANKS')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_LOGIN')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_MESSAGE_RATED')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_LABEL_VOTES')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_LABEL_VOTE')."','".
                   TEXT::_('PLG_CONTENT_EXTRAVOTE_LABEL_RATING').
               "');
            ");
            $plgContentExtraVoteAddScript = 1;
        }

        if($rating_count != 0) {
            $rating  = ($rating_sum / intval($rating_count));
            $add_snippets = $this->params->get('snippets', 0);
        } elseif($show_unrated == 0) {
            $show_counter = -1;
            $show_rating  = -1;
        }

        $container = 'div';
        $class     = 'size-'.$this->params->get('size', 1);
        if((int)$xid) {
            if ($show_counter == 2) {
                $show_counter = 0;
            }
            if ($show_rating == 2) {
                $show_rating = 0;
            }
            $container = 'span';
            $class    .= ' extravote-small';
            $add_snippets = 0;
        } else {
            if ($show_counter == 3) {
                $show_counter = 0;
            }
            if ($show_rating == 3) {
                $show_rating = 0;
            }
            $class    .= ' extravote';
        }
        if ($show_counter && $show_rating && $this->params->get('one_line', 0)) { // display all on 1 or 2 lines
            $class .= ' d-flex'; // one line
        }
        $stars = (($this->params->get('table', 1) != 1 && !(int)$xid) ? 5 : $this->params->get('stars', 10));
        $spans = '';
        for ($i = 0,$j = 5 / $stars; $i < $stars; $i++,$j += 5 / $stars) :
            $spans .= "
      <span class=\"extravote-star\"><a href=\"javascript:void(null)\" onclick=\"javascript:JVXVote(".$id.",".$j.",".$rating_sum.",".$rating_count.",'".$xid."',".$show_counter.",".$show_rating.",".$rating_mode.");\" title=\"".TEXT::_('PLG_CONTENT_EXTRAVOTE_RATING_'.($j * 10).'_OUT_OF_5')."\" class=\"ev-".($j * 10)."-stars\">1</a></span>";
        endfor;

        $html = "
<".$container." class=\"".$class."\">
  <div class=\"extravote-stars\"".">"."<span id=\"rating_".$id."_".$xid."\" class=\"current-rating\"".((!$initial_hide || $currip == $ip) ? " style=\"width:".round($rating * 20)."%;\"" : "")."></span>"
    .$spans."
  </div>
  <div class=\"extravote-info".(($initial_hide && $currip != $ip) ? " ihide\"" : "")."\" id=\"extravote_".$id."_".$xid."\">";

        if ($show_rating > 0) {
            if ($rating_mode == 0) {
                $rating = round($rating * 20) . '%';
            } else {
                $rating = number_format($rating, 2);
            }
            $html .= TEXT::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_RATING', $rating);
        }
        if ($show_counter > 0) {
            if($rating_count != 1) {
                $html .= TEXT::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_VOTES', $rating_count);
            } else {
                $html .= TEXT::sprintf('PLG_CONTENT_EXTRAVOTE_LABEL_VOTE', $rating_count);
            }
        }
        $html .= "</div>";
        $html .= "
</".$container.">";
        if ($add_snippets) {
            $html .= "<div class=\"visually-hidden\" itemscope=\"itemscope\" itemtype=\"http://schema.org/Product\">";
            $html .= "<span itemprop=\"name\">".$this->article_title."</span>";
            $html .= "<div class=\"visually-hidden\" itemprop=\"aggregateRating\" itemscope itemtype=\"http://schema.org/AggregateRating\">";
            $html .= "<meta itemprop=\"ratingCount\" content=\"".$rating_count."\" />";
            $html .= "<meta itemprop=\"ratingValue\" content=\"".$rating."\" />";
            $html .= "<meta itemprop=\"bestRating\" content=\"5\" />";
            $html .= "<meta itemprop=\"worstRating\" content=\"1\" />";
            $html .= "</div></div>";
        }

        return $html;
    }
    protected function ExtraVotePrepare($article, $params)
    {
        if (isset($this->article_id)) {
            $extra = $this->params->get('extra', 1);
            $main  = $this->params->get('main', 1);
            if ($extra != 0) {
                $regex = "/{extravote\s*([0-9]+)}/i";
                if ($this->view != 'article' && isset($article->introtext) && stripos($article->introtext, 'extravote')) {
                    if ($extra == 2) {
                        $article->introtext = preg_replace($regex, '', $article->introtext);
                    } else {
                        $article->introtext = preg_replace_callback($regex, array($this,'plgContentExtraVoteReplacer'), $article->introtext);
                    }
                } elseif (stripos($article->text, 'extravote') !== false) {
                    $article->text = preg_replace_callback($regex, array($this,'plgContentExtraVoteReplacer'), $article->text);
                }
            }
            if ($main != 0) {
                $strposIntro = isset($article->introtext) ? stripos($article->introtext, 'mainvote') : false;
                $strposText  = stripos($article->text, 'mainvote');
                $regex = "/{mainvote\s*([0-9]*)}/i";
                if ($main == 2 && $this->view != 'article' && $strposIntro) {
                    $article->introtext = preg_replace($regex, '', $article->introtext);
                } else {
                    $this->article_id = $article->id;
                    if ($this->view == 'article' && $strposText !== false) {
                        $article->text = preg_replace_callback($regex, array($this,'plgContentExtraVoteReplacer'), $article->text);
                    } elseif($strposIntro) {
                        $article->introtext = preg_replace_callback($regex, array($this,'plgContentExtraVoteReplacer'), $article->introtext);
                    }
                }
            }
            if ($this->params->get('display') == EXTRAVOTE_OPTION_AFTER_CONTENT) {
                $article->xid = 0;
                if ($this->view == 'article') {
                    $article->text .= $this->ContentExtraVote($article, $params);
                } elseif ($this->params->get('hide') == 0) {
                    $article->introtext .= $this->ContentExtraVote($article, $params);
                }
            }
        }
        return $article;
    }

    protected function plgContentExtraVoteReplacer($matches)
    {
        $db	 = $this->getDatabase();
        $cid = 0;
        $xid = 0;
        if (isset($matches[1])) {
            if(stripos($matches[0], 'extravote')) {
                $xid = (int)$matches[1];
            } else {
                $cid = (int)$matches[1];
            }
        }
        if ($cid == 0 && ($this->params->get('article_id') || $xid == 0)) {
            $cid = $this->article_id;
        }
        $rating_sum = 0;
        $rating_count = 0;
        if ($xid == 0) :
            global $extravote_mainvote;
            $extravote_mainvote .= 'x';
            $xid = $extravote_mainvote;
            $table = ($this->params->get('table', 1) == 1 ? '#__content_extravote' : '#__content_rating');
            $db->setQuery('SELECT * FROM ' . $table . ' WHERE content_id='.(int)$cid);
        else :
            $db->setQuery('SELECT * FROM #__content_extravote WHERE content_id='.(int)$cid.' AND extra_id='.(int)$xid);
        endif;
        $vote = $db->loadObject();
        if($vote) {
            if($vote->rating_count != 0) {
                $rating_sum = $vote->rating_sum;
            }
            $rating_count = intval($vote->rating_count);
        }
        return $this->plgContentExtraVoteStars($cid, $rating_sum, $rating_count, $xid, ($vote ? $vote->lastip : ''));
    }

    /**
     * Create SchemaOrg AggregateRating
     *
     * @param   object   $schema  The schema of the content being passed to the plugin
     * @param   string   $context The context of the content being passed to the plugin
     *
     * @return  void
     *
     * @since   5.0
     */
    public function beforeCompileHead($event): void
    {
        $add_snippets = $this->params->get('snippets', 0);
        if (!$add_snippets) {
            return;
        } // don't add snippet
        $schema = $event->getSchema();
        $context = $event->getContext();
        $graph    = $schema->get('@graph');
        $baseId   = Uri::root() . '#/schema/';
        $schemaId = $baseId . str_replace('.', '/', $context);

        foreach ($graph as &$entry) {
            if (!isset($entry['@type']) || !isset($entry['@id'])) {
                continue;
            }
            if ($entry['@id'] !== $schemaId) {
                continue;
            }
            if (isset($entry['aggregateRating'])) {
                return;
            } // already done

            switch ($entry['@type']) {
                case 'Book':
                case 'Brand':
                case 'CreativeWork':
                case 'Event':
                case 'Offer':
                case 'Organization':
                case 'Place':
                case 'Product':
                case 'Recipe':
                case 'Service':
                case 'alors':
                    $rating = $this->prepareAggregateRating($context);
                    break;
                case 'Article':
                case 'BlogPosting':
                    $rating = $this->prepareProductAggregateRating($context);
                    break;
            }
        }

        if (isset($rating) && $rating) {
            $graph[] = $rating;
            $schema->set('@graph', $graph);
        }
    }

    /**
     * Prepare AggregateRating
     *
     * @param   string $context
     *
     * @return  ?string
     *
     * @since  5.0
     */
    protected function prepareAggregateRating($context)
    {
        [$extension, $view, $id] = explode('.', $context);

        if ($view === 'article') {
            $baseId   = Uri::root() . '#/schema/';
            $schemaId = $baseId . str_replace('.', '/', $context);

            $component = $this->getApplication()->bootComponent('com_content')->getMVCFactory();
            $model     = $component->createModel('Article', 'Site');
            $article   = $model->getItem($id);
            $count     = $article->rating_count;
            $rating    = $article->rating;
            if ($this->params->get('table', 1)) { // use extravote table ?
                $this->getExtraVoteInfos($id, $count, $rating);
            }
            if ($count > 0) {
                return ['@isPartOf' => ['@id' => $schemaId, 'aggregateRating' => ['@type' => 'AggregateRating','ratingCount' => (string) $count,'ratingValue' => (string) $rating]]];
            }
        }

        return false;
    }

    /**
     * Prepare Product AggregateRating
     *
     * @param   string $context
     *
     * @return  ?string
     *
     * @since  5.0
     */
    protected function prepareProductAggregateRating($context)
    {
        [$extension, $view, $id] = explode('.', $context);

        if ($view === 'article') {
            $baseId   = Uri::root() . '#/schema/';
            $schemaId = $baseId . str_replace('.', '/', $context);

            $component = $this->getApplication()->bootComponent('com_content')->getMVCFactory();
            $model     = $component->createModel('Article', 'Site');
            $article   = $model->getItem($id);
            $count     = $article->rating_count;
            $rating    = $article->rating;
            if ($this->params->get('table', 1)) { // use extravote table ?
                $this->getExtraVoteInfos($id, $count, $rating);
            }
            if ($count > 0) {
                return ['@isPartOf' => ['@id' => $schemaId, '@type' => 'Product', 'name' => $article->title, 'aggregateRating' => ['@type' => 'AggregateRating', 'ratingCount' => (string) $count, 'ratingValue' => (string) $rating]]];
            }
        }

        return false;
    }
    protected function getExtraVoteInfos($id, &$count, &$rating)
    {
        $db	 = $this->getDatabase();
        $db->setQuery('SELECT * FROM #__content_extravote WHERE content_id='.(int)$id.' AND extra_id = 0');
        $vote = $db->loadObject();
        if($vote) {
            if($vote->rating_count != 0) {
                $rating = $vote->rating_sum;
            }
            $count = intval($vote->rating_count);
        }

    }

}

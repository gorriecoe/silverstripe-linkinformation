<?php

namespace gorriecoe\LinkInformation;

use SilverStripe\Dev\Debug;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\ToggleCompositeField;
use UncleCheese\DisplayLogic\Forms\Wrapper;
use Embed\Embed;

/**
 * Adds information about link such category, language and author
 *
 * @package silverstripe-linkinformation
 */
class LinkInformationExtension extends DataExtension
{
    /**
     * Database fields
     * All fields are Varchar for the sack of external links.
     * @var array
     */
    private static $db = [
        'AuthorName' => 'Varchar',
        'AuthorUrl' => 'Varchar',
        'Language' => 'Varchar',
        'Category' => 'Varchar',
        'PublishedDate' => 'Varchar',
        'License' => 'Varchar'
    ];

    /**
     * Display cms information if field type permits it.
     * @var array
     */
    private static $cms_information_if_type = [
        'URL',
        'File',
        'SiteTree'
    ];

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->owner;

        // Ensure these fields don't get added by fields scaffold
        $fields->removeByName([
            'AuthorName',
            'AuthorUrl',
            'Language',
            'Category',
            'PublishedDate',
            'License'
        ]);

        $fields->addFieldsToTab(
            'Root.Main',
            [
                $InformationWrapper = Wrapper::create(
                    ToggleCompositeField::create(
                        'Information',
                        _t(__CLASS__ . '.INFORMATION', 'Information'),
                        [
                            ReadonlyField::create(
                                'AuthorName',
                                _t(__CLASS__ . '.AUTHORNAME', 'Author name')
                            ),
                            ReadonlyField::create(
                                'AuthorUrl',
                                _t(__CLASS__ . '.AUTHORURL', 'Author link')
                            ),
                            ReadonlyField::create(
                                'Language',
                                _t(__CLASS__ . '.LANGUAGE', 'Language')
                            ),
                            ReadonlyField::create(
                                'Category',
                                _t(__CLASS__ . '.CATEGORY', 'Category')
                            ),
                            ReadonlyField::create(
                                'PublishedDate',
                                _t(__CLASS__ . '.PUBLISHEDDATE', 'Published date')
                            ),
                            ReadonlyField::create(
                                'License',
                                _t(__CLASS__ . '.LICENSE', 'License')
                            )
                        ]
                    )
                )

            ]
        );

        $types = $owner->config()->get('cms_information_if_type');
        $first =  true;
        foreach ($types as $type) {
            if ($first) {
                $InformationWrapper = $InformationWrapper->displayIf('Type')->isEqualTo($type);
                $first = false;
            } else {
                $InformationWrapper = $InformationWrapper->orIf('Type')->isEqualTo($type);
            }
        };
        $InformationWrapper->end();

        return $fields;
    }

    /**
     * Event handler called before writing to the database.
     */
    public function onBeforeWrite()
    {
        $owner = $this->owner;
        $type = $owner->Type;

        // If type change clear all information.
        if ($owner->isChanged('Type')) {
            $owner->AuthorName = null;
            $owner->AuthorUrl = null;
            $owner->Language = null;
            $owner->PublishedDate = null;
            $owner->License = null;
            $owner->Category = null;
        }

        switch ($type) {
            case 'URL':
                if ($sourceURL = $owner->URL) {
                    $information = Embed::create($sourceURL);
                    $owner->AuthorName = $information->AuthorName;
                    $owner->AuthorUrl = $information->AuthorUrl;
                    $owner->Language = $information->Language;
                    $owner->PublishedDate = $information->PublishedDate;
                    $owner->License = $information->License;
                    if ($information->Type == 'photo') {
                        $owner->Category = 'Image';
                    } else {
                        $owner->Category = ucfirst($information->Type);
                    }
                }
                break;
            case 'Email':
            case 'Phone':
                $owner->Category = $type;
                break;
            case 'File':
                if ($file = $owner->File() && $file->exists()) {
                    if ($file->hasMethod('appCategory')) {
                        $owner->Category = $file->appCategory();
                    }
                }
                break;
            case 'SiteTree':
                $owner->Category = 'Link';
                break;
        }
    }

    public function getAuthorName()
    {
        $owner = $this->owner;
        $type = $owner->Type;
        $value = null;
        switch ($type) {
            case 'URL':
                $value = $owner->getField('AuthorName');
                break;
            case 'File':
                $member = $owner->getComponent($type)->Owner();
                if ($member->hasField('AuthorName')) {
                    $value = $member->getField('AuthorName');
                } else {
                    $value = $member->getField('FirstName');
                }
                break;
            case 'SiteTree':
                $page = $owner->getComponent($type);
                if ($page->hasField('AuthorName')) {
                    $value = $page->getField('AuthorName');
                }
                break;
        }
        $owner->extend('updateAuthorName', $value);
        return $value;
    }

    public function getPublishedDate()
    {
        $owner = $this->owner;
        $type = $owner->Type;
        $value = null;
        switch ($type) {
            case 'URL':
                $value = $owner->getField('PublishedDate');
                break;
            case 'File':
            case 'SiteTree':
                if ($owner->hasField('PublishDate')) {
                    $value = $owner->getField('PublishDate');
                } else {
                    $value = $owner->getField('LastEdited');
                }
                break;
        }
        $owner->extend('updatePublishDate', $value);
        return $value;
    }
}

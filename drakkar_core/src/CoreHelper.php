<?php


namespace Drupal\drakkar_core;

use \Drupal\taxonomy\Entity\Term;

class CoreHelper {

  protected $exclude = [
    'id',
    'uuid',
    'vid',
    'changed',
    'promote',
    'revision_timestamp',
    'revision_log',
    'sticky',
    'path',
    'revision_id',
    'langcode',
    'type',
    'uid',
    'status',
    'created',
    'revision_uid',
    'parent_id',
    'parent_field_name',
    'behavior_settings',
    'default_langcode',
    'revision_default',
    'revision_translation_affected',
    'content_translation_source',
    'content_translation_outdated',
    'content_translation_changed',
  ];
  
  /**
   * Schema can be found here: https://drakkar-ws.herokuapp.com
   * @param $node
   *
   * @return array
   */
  public function getNodeFields($node, $included_fields) {
      $nodetype = $node->getType();
      $entityManager = \Drupal::service('entity_field.manager');
      $bundle_fields = $entityManager->getFieldDefinitions('node', $nodetype);
      $fields_value = $this->getNodeValues($node, $bundle_fields);
      if (!empty($included_fields)) {
        $fields = [];
        foreach ($included_fields as $field) {
          $label = strtolower($fields_value[$field]['label']);
          $fields[$label] = [
            'type' => $fields_value[$field]['type'],
            'content' => $fields_value[$field]['content'],
          ];
        }

        return $fields;
      }
      else {
        return $fields_value;
      }
  }

  /**
   * Get node values.
   *
   * @param $node
   * @param $fields
   * @param $exclude
   *
   * @return mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNodeValues($node, $fields) {
    foreach($fields as $fieldId => $field) {

      if (in_array($fieldId, $this->exclude)) {
        continue;
      }

      $label = $field->getLabel();
      $type = $field->getType();
      $values = [];

      switch($type) {
        case 'entity_reference_revisions':
          $paraType = '';
          $paras = $node->get($fieldId)->referencedEntities();
          if (!empty($paras)) {
            foreach ($paras as $para) {
              $paraType = $para->getType();

              //- Skip the hero since we put that higher up in the response
              //- continue 3 tells PHP to break out of the loop all the way back to the first foreach (since we're in foreach, switch and foreach)
              if ($paraType == 'hero') continue 3;

              $entityManager = \Drupal::service('entity_field.manager');
              $bundle_fields = $entityManager->getFieldDefinitions('paragraph', $paraType);

              if ($paraType == 'page_section') {
                $values[] = $this->getParagraphPageSection($para);
              } else {
                $values[] = $this->getNodeValues($para, $bundle_fields);
              }

            }
          }
          break;
        case 'entity_reference':
          $entities = $node->get($fieldId)->referencedEntities();
          foreach ($entities as $entity) {
            $values[] = [
              'name' => $entity->get('name')->value,
              'id' => $entity->id(),
            ];
          }
          break;

        case 'link':
          $values = $node->get($fieldId)->value;
          break;

        case 'image':
          $fid = $node->get($fieldId)->target_id;
          $file_storage = \Drupal::entityTypeManager()->getStorage('file');
          $file = $file_storage->load($fid);
          if ($file) {
            $values['url'] = file_url_transform_relative(file_create_url($file->getFileUri()));
          }
          break;

        case 'file':
          $values = $node->get($fieldId)->value;
          break;

        case 'list_float':
          $values = $node->get($fieldId)->value;
          break;

        case 'list_integer':
          $values = $node->get($fieldId)->value;
          break;

        case 'list_string':
          $values = $node->get($fieldId)->value;
          break;

        case 'text_with_summary':
          $values = [
            'format' => $node->get($fieldId)->format,
            'summary' => $node->get($fieldId)->summary,
            'value' => $node->get($fieldId)->value
          ];

          break;

        default:
          $values = $node->get($fieldId)->value;
          break;
      }

      if (!empty($paraType)) {
        $result[$fieldId] = [
          'label' => $label,
          'type' => $paraType,
          'content' => $values
        ];
      }
      else {
        $result[$fieldId] = [
          'label' => $label,
          'type' =>  $type,
          'content' => $values
        ];
      }
    }
    return $result;
  }

  /**
   * Get field values from the hero paragraph
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphHero($paragraph) {
    $slides = [];
    $theme = NULL;

    if (!$paragraph->field_para_reference->isEmpty()) {
      foreach ($paragraph->field_para_reference as $para) {
        $para = $para->entity;
        $paraType = $para->getType();
        $paraUUID = $para->get('uuid')->value;
        if ($paraType == 'slide') {
          $slides[$paraUUID] = [
            'type' => 'slide',
            'content' => $this->getParagraphSlides($paragraph)
          ];
        }
      }
    }

    if (!$paragraph->get('field_theme')->isEmpty()) {
      $theme = $paragraph->field_theme->value;
    }

    $result = [
      'theme' => $theme,
      'slides' => $slides
    ];

    return $result;
  }

  /**
   * @param $paragraph
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getParagraphPageSection($paragraph) {
    $result = [];

    $result['style'] = [
      'sectionTitleStyle' => ! $paragraph->field_section_title_style->isEmpty() ? $paragraph->field_section_title_style->value : '',
      'backgroundColor'   => [
        'value' => ! $paragraph->field_tax_reference->isEmpty() ? \Drupal\taxonomy\Entity\Term::load($paragraph->field_tax_reference->target_id)
          ->getName() : ''
      ],
      'bottomPadding'     => ! $paragraph->field_anchor->isEmpty() ? $paragraph->field_section_bottom_padding->value : '',
      'highlightColor'    => [
        'value' => ! $paragraph->field_tax_reference_highlight->isEmpty() ? \Drupal\taxonomy\Entity\Term::load($paragraph->field_tax_reference_highlight->target_id)
          ->getName() : ''
      ]
    ];

    foreach ($paragraph->field_separator as $separator) {
      $result['style']['separators'][] = $separator->value;
    }

    $result['content'] = [
      'anchor' => ! $paragraph->field_anchor->isEmpty() ? $paragraph->field_anchor->value : '',
      'title'  => ! $paragraph->field_text->isEmpty() ? $paragraph->field_text->value : '',
      'cta'    => ! $paragraph->field_cta->isEmpty() ? $paragraph->field_cta->first()
        ->getValue() : '',
    ];

    if (!$paragraph->field_para_reference->isEmpty()) {
      $paragraphs = [];

      foreach ($paragraph->field_para_reference as $para) {
        $paraEntity = $para->entity;
        $paraType = $para->entity->getType();
        $paraUUID = $paraEntity->get('uuid')->value;
        switch ($paraType) {
          case '2_columns_image_and_text':
            $paragraphs[$paraUUID] = $this->getTwoColumnsImageText($paraEntity);
            break;

          case 'accordion_group' :
            $paragraphs[$paraUUID] = $this->getParagraphAccordion($paraEntity);
            break;

          case 'button':
            $paragraphs[$paraUUID] = $this->getParagraphButton($paraEntity);
            break;

          case 'form':
            $paragraphs[$paraUUID] = $this->getParagraphForm($paraEntity);
            break;

          case 'gallery':
            $paragraphs[$paraUUID] = $this->getParagraphGallery($paraEntity);
            break;

          case 'icon_and_text':
            $paragraphs[$paraUUID] = $this->getParagraphIconAndText($paraEntity);
            break;

          case 'image':
            $paragraphs[$paraUUID] = $this->getParagraphImage($paraEntity);
            break;

          case 'image_and_text_cta':
            $paragraphs[$paraUUID] = $this->getParagraphImageAndTextCTA($paraEntity);
            break;

          case 'image_and_text_cta_drawer':
            $paragraphs[$paraUUID] = $this->getParagraphImageTextCTADrawer($paraEntity);
            break;

          case 'inspiration_banner':
            $paragraphs[$paraUUID] = $this->getParagraphInspirationBanner($paraEntity);
            break;

          case 'logo_group':
            $paragraphs[$paraUUID] = $this->getParagraphVectorItem('field_para_reference', $paraEntity, 'logo_group');
            break;

          case 'map':
            $paragraphs[$paraUUID] = $this->getParagraphMap($paraEntity);
            break;

          case 'testimonials_group':
            $paragraphs[$paraUUID] = $this->getParagraphTestimonialsGroup($paraEntity);
            break;

          case 'text_block':
            $paragraphs[$paraUUID] = $this->getParagraphTextBlock($paraEntity);
            break;

          case 'article_reference':
            $paragraphs[$paraUUID] = $this->getParagraphToolboxArticleReference($paraEntity);
            break;

          case 'vector_item':
            $paragraphs[$paraUUID] = $this->getParagraphVectorItem('field_para_reference', $paraEntity, 'vector_item');
            break;

          case 'region_cta':
            $paragraphs[$paraUUID] = $this->getParagraphVectorItem('field_para_reference_region', $paraEntity, 'region_cta');
            break;

          case 'video':
            $paragraphs[$paraUUID] = $this->getParagraphVideo($paraEntity);
            break;

        }

      }
      $result['content']['paragraphs'] = $paragraphs;
    }

    return $result;
  }


  /**
   * Get slide values from the slide paragraph
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphSlides($paragraph) {
    $result = [];

    foreach ($paragraph->field_para_reference as $para) {
      $para = $para->entity;
      $result[] =
        [
          'content' => [
            'title' => !$para->field_wysiwyg->isEmpty() ? $para->field_wysiwyg->value : '',
            'imageSingle' => [
              'url' => !$para->field_image_single->isEmpty() ? file_url_transform_relative(file_create_url($para->field_image_single->entity->getFileUri())) : ''
            ],
            'cta' => !$para->field_cta->isEmpty() ? $para->field_cta->first()->getValue() : '',
          ],
          'style' => [
            'highlightColor' => [
              'value' => !$para->field_tax_reference->isEmpty() ? Term::load($para->field_tax_reference->target_id)->getName() : ''
            ]
          ]
        ];
    }

    return $result;
  }

  /**
   * Get field values from TwoColumnsImageText paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getTwoColumnsImageText($paragraph) {
    $result = [
      'type' => '2_columns_image_and_text',
      'content' => [
        'title' => !$paragraph->field_text->isEmpty() ? $paragraph->field_text->value : '',
        'columnOne' => [],
        'columnTwo' => []
      ],
      'style' => !$paragraph->field_column_padding->isEmpty() ? $paragraph->field_column_padding->value : ''

    ];



    foreach ($paragraph->field_para_reference as $para) {
      $para = $para->entity;
      $paraType = $para->getParagraphType();

      //- This would be an image paragraph reference
      if ($paraType->id == 'image') {
        $result['content']['columnOne'][] = $this->getParagraphImage($para);
      }

      //- This would be a text block
      if ($paraType->id == 'text_block') {
        $result['content']['columnOne'][] = $this->getParagraphTextBlock($para);
      }
    }

    foreach ($paragraph->field_para_reference_2 as $para) {
      $para = $para->entity;
      $paraType = $para->getParagraphType();

      //- This would be an image paragraph reference
      if ($paraType->id == 'image') {
        $result['content']['columnTwo'][] = $this->getParagraphImage($para);
      }

      //- This would be a text block
      if ($paraType->id == 'text_block') {
        $result['content']['columnTwo'][] = $this->getParagraphTextBlock($para);
      }
    }

    return $result;
  }

  /**
   * Get field values from Accordion paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphAccordion($paragraph) {
    $result = [];

    foreach ($paragraph->field_para_reference as $para) {
      $para = $para->entity;
      $result['type'] = 'accordion_group';
      $result['content'][] = [
        'title' => $para->field_text->value,
        'content' => $para->field_wysiwyg->value,
        'numberingText' => $para->field_numbering_text->value
      ];
    }

    return $result;
  }

  /**
   * Get field values from Button paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphButton($paragraph) {
    //- Button paragraphs are added individually to a page section, not as a paragraph containing other paragraphs
    //- so we don't need to iterate over the paragraph coming in, just grab the values and go

    $result =
      [
        'type' => 'button',
        'content' => [
          'cta' => !$paragraph->field_cta->isEmpty() ? $paragraph->field_cta->first()->getValue() : '',
          'position' => !$paragraph->field_position->isEmpty() ? $paragraph->field_position->value : ''
        ],
      ];

    return $result;
  }

  /**
   * Get field values from Form paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphForm($paragraph) {
    $result = [];

    foreach ($paragraph->field_para_reference as $para) {
      $para = $para->entity;
      $result[] =
        [
          'type' => 'form',
          'content' => [
            'form' => !$para->field_text->isEmpty() ? $para->field_text->value : ''
          ],
        ];
    }

    return $result;
  }

  /**
   * Get field values from Gallery paragraph.
   * @param $paragraph
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getParagraphGallery($paragraph) {
    $result = [];

    foreach ($paragraph->field_image as $image) {
      $fid = $image->target_id;
      $file_storage = \Drupal::entityTypeManager()->getStorage('file');
      $file = $file_storage->load($fid);

      if ($file) {
        $result['type'] = 'gallery';
        $result['content']['images'][] = [
          'url' => file_url_transform_relative(file_create_url($file->getFileUri()))
        ];
      }
    }

    return $result;
  }

  /**
   * Get field values from Image paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphImage($paragraph) {
    //- Image paragraphs are added individually to a page section, not as a paragraph containing other paragraphs
    //- so we don't need to iterate over the paragraph coming in, just grab the values and go

    $result =
      [
        'type' => 'image',
        'content' => [
          'image' => [
            'url' => !$paragraph->field_image_single->isEmpty() ? file_url_transform_relative(file_create_url($paragraph->field_image_single->entity->getFileUri())) : ''
          ],
          'imageStyle' => !$paragraph->field_image_style->isEmpty() ? $paragraph->field_image_style->value : ''
        ]
      ];

    return $result;
  }

  /**
   * Get field values from ImageAndTextCTA paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphImageAndTextCTA($paragraph) {
    //- Image and text CTA paragraphs are added individually to a page section, not as a paragraph containing other paragraphs
    //- so we don't need to iterate over the paragraph coming in, just grab the values and go

    $result =
      [
        'type' => 'image_and_text_cta',
        'content' => [
          'title'=> !$paragraph->field_text->isEmpty() ? $paragraph->field_text->value : '',
          'subtitle' => !$paragraph->field_subtitle->isEmpty() ? $paragraph->field_subtitle->value : '',
          'cta' => !$paragraph->field_cta->isEmpty() ? $paragraph->field_cta->first()->getValue() : '',
          'image' => [
            'url' => !$paragraph->field_image_single->isEmpty() ? file_url_transform_relative(file_create_url($paragraph->field_image_single->entity->getFileUri())) : ''
          ],
          'style' => [
            'theme' => !$paragraph->field_theme_cta->isEmpty() ? $paragraph->field_theme_cta->value : '',
            'highlightColor' => [
              'value' => !$paragraph->field_tax_reference->isEmpty() ? Term::load($paragraph->field_tax_reference->target_id)->getName() : ''
            ],
          ]
        ]
      ];

    return $result;
  }

  /**
   * Get field values from ImageTextCTADrawer paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphImageTextCTADrawer($paragraph) {
    //- Image and text drawers are added individually to a page section, not as a paragraph containing other paragraphs
    //- so we don't need to iterate over the paragraph coming in, just grab the values and go
    $result =
      [
        'type' => 'image_and_text_cta_drawer',
        'content' => [
          'cta' => !$paragraph->field_cta->isEmpty() ? $paragraph->field_cta->first()->getValue() : '',
          'image' => [
            'url' => !$paragraph->field_image_single->isEmpty() ? file_url_transform_relative(file_create_url($paragraph->field_image_single->entity->getFileUri())) : ''
          ],
          'title' => !$paragraph->field_text->isEmpty() ? $paragraph->field_text->value : '',
          'links' => !$paragraph->field_wysiwyg->isEmpty() ? $paragraph->field_wysiwyg->value : '',
          'subtitle' => !$paragraph->field_subtitle->isEmpty() ? $paragraph->field_subtitle->value : ''
        ],
        'style' => [
          'highlightColor' => [
            'value' => !$paragraph->field_tax_reference->isEmpty() ? \Drupal\taxonomy\Entity\Term::load($paragraph->field_tax_reference->target_id)->getName() : ''
          ],
        ]
      ];

    return $result;
  }

  /**
   * Get field values from InspirationBanner paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphInspirationBanner($paragraph) {
    //- Inspiration banner paragraphs are added individually to a page section, not as a paragraph containing other paragraphs
    //- so we don't need to iterate over the paragraph coming in, just grab the values and go

    $result =
      [
        'type' => 'inspiration_banner',
        'content' => [
          'title' => !$paragraph->field_text->isEmpty() ? $paragraph->field_text->value : '',
          'subtitle' => !$paragraph->field_subtitle->isEmpty() ? $paragraph->field_subtitle->value : '',
          'image' => [
            'url' => (isset($paragraph->field_image_single) && !$paragraph->field_image_single->isEmpty()) ? file_url_transform_relative(file_create_url($paragraph->field_image_single->entity->getFileUri())) : ''
          ],
          'cta' => !$paragraph->field_cta->isEmpty() ? $paragraph->field_cta->first()->getValue() : ''
        ],
      ];

    return $result;
  }

  /**
   * Get field values IconAndText paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphIconAndText($paragraph) {
    //- Icon and text drawers are added individually to a page section, not as a paragraph containing other paragraphs
    //- so we don't need to iterate over the paragraph coming in, just grab the values and go
    //- except for the icon

    $icon = !$paragraph->field_tax_reference->isEmpty() ? \Drupal\taxonomy\Entity\Term::load($paragraph->field_tax_reference->target_id) : null;

    if ($icon && $icon->hasField('field_image')) {
      if (!$icon->field_image->isEmpty()) {
        $image = file_url_transform_relative(file_create_url($icon->field_image->entity->getFileUri()));
      }
    }

    $result =
      [
        'type' => 'icon_and_text',
        'content' => [
          'title' => !$paragraph->field_text->isEmpty() ? $paragraph->field_text->value : '',
          'image' => [
            'url' => $image ? $image : ''
          ],
          'subtitle' => !$paragraph->field_subtitle->isEmpty() ? $paragraph->field_subtitle->value : ''
        ]
      ];

    return $result;
  }

  /**
   * Get field values from Map paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphMap($paragraph) {
    //- Map paragraphs are added individually to a page section, not as a paragraph containing other paragraphs
    //- so we don't need to iterate over the paragraph coming in, just grab the values and go

    $map = null;
    if (!$paragraph->field_map->isEmpty()) {
      $map = $paragraph->field_map->first()->getValue();
    }

    $result =
      [
        'type' => 'map',
        'content' => [
          'map' => [
            'lat' => $map != null ? $map['lat'] : '',
            'lng' => $map != null ? $map['lng'] : ''
          ],
          'label' => !$paragraph->field_text->isEmpty() ? $paragraph->field_text->value : '',
          'city' => !$paragraph->field_city->isEmpty() ? $paragraph->field_city->value : '',
          'address' => !$paragraph->field_subtitle->isEmpty() ? $paragraph->field_subtitle->value : '',
          'phone' => !$paragraph->field_phone->isEmpty() ? $paragraph->field_phone->value : '',
          'tollFreeNumber' => !$paragraph->field_toll_free_number->isEmpty() ? $paragraph->field_toll_free_number->value : '',
          'fax' => !$paragraph->field_fax->isEmpty() ? $paragraph->field_fax->value : '',
        ],
      ];

    return $result;
  }

  /**
   * Get field values from TestimonialsGroup paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphTestimonialsGroup($paragraph) {
    $result = [];

    foreach ($paragraph->field_content_reference as $para) {
      $para = $para->entity;
      $result['type'] = 'testimonials_group';
      $result['content'][] =
        [
          'name' => !$para->field_name->isEmpty() ? $para->field_name->value : '',
          'position' => !$para->field_job_position->isEmpty() ? $para->field_job_position->value : '',
          'company' => !$para->field_company->isEmpty() ? $para->field_company->value : '',
          'quote' => !$para->field_quote->isEmpty() ? $para->field_quote->value : '',
        ];
    }

    return $result;
  }

  /**
   * Get field values from TextBlock paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphTextBlock($paragraph) {
    //- Text blocks are added individually to a page section, not as a paragraph containing other paragraphs
    //- so we don't need to iterate over the paragraph coming in, just grab the values and go
    $result =
      [
        'type' => 'text_block',
        'content' => [
          'text'    => ! $paragraph->field_wysiwyg->isEmpty() ? $paragraph->field_wysiwyg->value : ''
        ],
        'style' => [
          'padding' => ! $paragraph->field_padding->isEmpty() ? $paragraph->field_padding->value : ''
        ]
      ];

    return $result;
  }

  /**
   * Get field values from ToolboxArticleReference paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphToolboxArticleReference($paragraph) {
    $result = [];

    foreach ($paragraph->field_content_reference_toolbox as $para) {
      $node = $para->entity;
      $result['type'] = 'article_reference';
      $result['content'][] =
        [
          'title' => $node->title->value,
          'image' => !$node->field_image->isEmpty() ? file_url_transform_relative(file_create_url($node->field_image->entity->getFileUri())) : '',
          'teaser' => !$node->field_text->isEmpty() ? $node->field_text->value : '',
        ];
    }

    return $result;

  }

  /**
   * Get field values from VectorItem paragraph.
   * @param $referenceField
   * @param $paragraph
   * @param $type
   *
   * @return array
   */
  public function getParagraphVectorItem($referenceField, $paragraph, $type) {
    $result = [];

    foreach ($paragraph->$referenceField as $para) {
      $para = $para->entity;
      $result[] =
        [
          'type' => $type,
          'content' => [
            'cta' => !$para->field_cta->isEmpty() ? $para->field_cta->first()->getValue() : '',
            'image' => [
              'url' => !$para->field_image_single->isEmpty() ? file_url_transform_relative(file_create_url($para->field_image_single->entity->getFileUri())) : ''
            ],
          ],
        ];
    }

    return $result;
  }

  /**
   * Get field values from Video paragraph.
   * @param $paragraph
   *
   * @return array
   */
  public function getParagraphVideo($paragraph) {
    //- Video paragraphs are added individually to a page section, not as a paragraph containing other paragraphs
    //- so we don't need to iterate over the paragraph coming in, just grab the values and go
    $result =
      [
        'type' => 'video',
        'content' => [
          'id'    => ! $paragraph->field_text->isEmpty() ? $paragraph->field_text->value : ''
        ]
      ];

    return $result;
  }
}
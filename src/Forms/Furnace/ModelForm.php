<?php

declare(strict_types=1);

namespace IronFlow\Forms\Furnace;

use IronFlow\Database\Collection;
use IronFlow\View\Component;

use IronFlow\Database\Model;
use IronFlow\Support\Helpers;
use IronFlow\Forms\Furnace\HasForm;

use IronFlow\Forms\Furnace\Components\Checkbox;
use IronFlow\Forms\Furnace\Components\ColorPicker;
use IronFlow\Forms\Furnace\Components\DatePicker;
use IronFlow\Forms\Furnace\Components\File;
use IronFlow\Forms\Furnace\Components\Input;
use IronFlow\Forms\Furnace\Components\Radio;
use IronFlow\Forms\Furnace\Components\Select;
use IronFlow\Forms\Furnace\Components\Textarea;
use IronFlow\View\Components\UI\Button;

/**
 * Classe qui lie un model à un formulaire
 */
class ModelForm extends Component
{
   protected Model $model;
   protected string $action = '';
   protected string $method = 'POST';
   protected array $fields = [];
   protected array|Collection $data = [];
   protected bool $hasValidation = false;

   public function __construct(Model $model)
   {
      parent::__construct([]);
      $this->model = $model;
      $this->hasValidation = in_array(HasForm::class, Helpers::classUsesRecursive($model));
   }

   /**
    * Ajouter l'action de traitement du formulaire
    * @param string $action
    * @return ModelForm
    */
   public function action(string $action): self
   {
      $this->action = $action;
      return $this;
   }

   /**
    * Ajputer la methode du formulaire
    * @param string $method
    * @return ModelForm
    */
   public function method(string $method): self
   {
      $this->method = strtoupper($method);
      return $this;
   }

   /**
    * Ajouter un champs de type input
    * @param string $name
    * @param string $label
    * @param string $type
    * @return ModelForm
    */
   public function input(string $name, string $label = '', string $type = 'text'): self
   {
      $input = new Input($name, $label);
      $input->type($type);

      if ($this->hasValidation) {
         /** @var HasForm $model */
         $error = $this->model->getFieldError($name);
         if ($error) {
            $input->withError($error);
         }
      }

      $this->fields[$name] = $input;
      return $this;
   }

   /**
    * Ajouter un champs de type textarea
    * @param string $name
    * @param string $label
    * @return ModelForm
    */
   public function textarea(string $name, string $label = ''): self
   {
      $textarea = new Textarea($name, $label);

      if ($this->hasValidation) {
         /** @var HasForm $model */
         $error = $this->model->getFieldError($name);
         if ($error) {
            $textarea->withError($error);
         }
      }

      $this->fields[$name] = $textarea;
      return $this;
   }

   /**
    * Ajouter un champ de type select
    * @param string $name
    * @param string $label
    * @param array|\IronFlow\Database\Collection $options
    * @return ModelForm
    */
   public function select(string $name, string $label = '', array|Collection $options = []): self
   {
      $select = new Select($name, $label);
      if (!empty($options)) {
         $select->options($options);
      }

      if ($this->hasValidation) {
         /** @var HasForm $model */
         $error = $this->model->getFieldError($name);
         if ($error) {
            $select->withError($error);
         }
      }

      $this->fields[$name] = $select;
      return $this;
   }

   /**
    * Ajouter un champs de type checkbox
    * @param string $name
    * @param string $label
    * @param array $options
    * @return ModelForm
    */
   public function checkbox(string $name, string $label = '', array $options = []): self
   {
      $checkbox = new Checkbox($name, $label);
      if (!empty($options)) {
         $checkbox->options($options);
      }

      if ($this->hasValidation) {
         /** @var HasForm $model */
         $error = $this->model->getFieldError($name);
         if ($error) {
            $checkbox->withError($error);
         }
      }

      $this->fields[$name] = $checkbox;
      return $this;
   }

   /**
    * Ajouter une champs de type radio
    * @param string $name
    * @param string $label
    * @param array $options
    * @return ModelForm
    */
   public function radio(string $name, string $label = '', array $options = []): self
   {
      $radio = new Radio($name, $label);
      if (!empty($options)) {
         $radio->options($options);
      }

      if ($this->hasValidation) {
         /** @var HasForm $model */
         $error = $this->model->getFieldError($name);
         if ($error) {
            $radio->withError($error);
         }
      }

      $this->fields[$name] = $radio;
      return $this;
   }

   /**
    * Ajouter un champs de type file
    * @param string $name
    * @param string $label
    * @return ModelForm
    */
   public function file(string $name, string $label = ''): self
   {
      $file = new File($name, $label);

      if ($this->hasValidation) {
         /** @var HasForm $model */
         $error = $this->model->getFieldError($name);
         if ($error) {
            $file->withError($error);
         }
      }

      $this->fields[$name] = $file;
      return $this;
   }

   /**
    * Ajouter un champs de type date
    * @param string $name
    * @param string $label
    * @return ModelForm
    */
   public function date(string $name, string $label = ''): self
   {
      $date = new DatePicker($name, $label);

      if ($this->hasValidation) {
         /** @var HasForm $model */
         $error = $this->model->getFieldError($name);
         if ($error) {
            $date->withError($error);
         }
      }

      $this->fields[$name] = $date;
      return $this;
   }

   /**
    * Ajouter un champs de type color
    * @param string $name
    * @param string $label
    * @return ModelForm
    */
   public function color(string $name, string $label = ''): self
   {
      $color = new ColorPicker($name, $label);

      if ($this->hasValidation) {
         /** @var HasForm $model */
         $error = $this->model->getFieldError($name);
         if ($error) {
            $color->withError($error);
         }
      }

      $this->fields[$name] = $color;
      return $this;
   }

   /**
    * Ajouter un boutton au formulaire
    * @param string $label
    * @param mixed $type
    * @param mixed $variant
    * @param mixed $size
    * @param mixed $fullWidth
    * @param mixed $icon
    * @param mixed $disabled
    * @param mixed $loading
    * @param mixed $attributes
    * @return ModelForm
    */
   public function button(string $label = 'Enregistrer', $type = 'submit', $variant = 'primary', $size = 'lg', $fullWidth = true, $icon = null, $disabled = false, $loading = false, $attributes = []): self
   {
      $button = new Button(['name' => 'submit'])
         ->type($type)
         ->variant($variant)
         ->size($size)
         ->fullWidth($fullWidth)
         ->icon($icon)
         ->disabled($disabled)
         ->loading($loading)
         ->withAttributes($attributes)
         ->setContent($label);
         
      $this->fields['submit'] = $button;
      return $this;
   }

   public function fill($data)
   {
      
      
      foreach($this->fields as $key => $field) {
         if (in_array($key, array_keys($data))){
            $field->value = $data[$key];
         }
      }
      return $this;
   }

   /**
    * Rendre le HTML du formulaire
    * @return string
    */
   public function render(): string
   {
      $template = '<form action="%s" method="%s" class="space-y-4">';

      if ($this->method === 'PUT' || $this->method === 'DELETE') {
         $template .= '<input type="hidden" name="_method" value="' . $this->method . '">';
         $this->method = 'POST';
      }

      if ($this->hasValidation) {
         /** @var HasForm $model */
         if ($this->model->hasFormErrors()) {
            $template .= '<div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
               <div class="flex">
                  <div class="flex-shrink-0">
                     <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                     </svg>
                  </div>
                  <div class="ml-3">
                     <p class="text-sm text-red-700">
                        Veuillez corriger les erreurs ci-dessous.
                     </p>
                  </div>
               </div>
            </div>';
         }
      }

      $template .= '%s'; // Contenu du formulaire
      $template .= '</form>';

      return sprintf($template, $this->action, $this->method, $this->renderFields());
   }

   protected function renderFields(): string
   {
      return implode("\n", array_map(function ($field) {
         if ($field instanceof Component) {
            return $field->render();
         } elseif (is_string($field)) {
            return $field;
         }
         return '';
      }, $this->fields));
   }
}

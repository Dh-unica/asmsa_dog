<?php

namespace Drupal\dog;

/**
 * Defines the OmekaApiResponse class.
 *
 * @package Drupal\dog
 */
class OmekaApiResponse {

  /**
   * The content of response.
   *
   * @var array
   */
  protected $content = [];

  /**
   * Total results that indicates the total number of results across all pages.
   *
   * @var int
   */
  protected $total_results = 0;

  /**
   * Construct new OmekaApiResponse instance.
   *
   * @param mixed $content
   *   The content of response from API..
   * @param int $total_results
   *   Total results that indicates the total number of results across all pages.
   */
  public function __construct($content, int $total_results = 1) {
    $this->content = $content;
    $this->total_results = $total_results;
  }

  /**
   * Retrieve the content of response (how array).
   *
   * @return array
   *   An array contains the data.
   */
  public function getContent(): array {

    // Convert to array.
    return json_decode($this->content, TRUE);
  }

  /**
   * Retrieve the total result accross all pages.
   *
   * @return int
   *   The total result.
   */
  public function getTotalResults(): int {
    return $this->total_results;
  }

  /**
   * Set the total results accross all pages.
   *
   * @param int $total_results
   *   The number of results.
   */
  public function setTotalResults(int $total_results): void {
    $this->total_results = $total_results;
  }
  
  /**
   * Verifica se la risposta contiene un errore.
   *
   * @return bool
   *   TRUE se la risposta contiene un errore, FALSE altrimenti.
   */
  public function hasError(): bool {
    $content = $this->getContent();
    
    // Verifica se contiene un campo 'error' o se è vuoto/nullo
    if (empty($content) || isset($content['error'])) {
      return TRUE;
    }
    
    // Verifica se il contenuto è un array con elementi o un oggetto valido
    if (!is_array($content) || empty($content)) {
      return TRUE;
    }
    
    return FALSE;
  }
  
  /**
   * Ottiene il messaggio di errore dalla risposta.
   *
   * @return string
   *   Il messaggio di errore, o una stringa vuota se non c'è errore.
   */
  public function getErrorMessage(): string {
    $content = $this->getContent();
    
    if (isset($content['error'])) {
      return $content['error'];
    }
    
    if (isset($content['errors']) && is_array($content['errors'])) {
      return implode(", ", $content['errors']);
    }
    
    if ($this->hasError()) {
      return 'Errore API generico: risposta non valida o vuota';
    }
    
    return '';
  }
  
  /**
   * Ottiene i dati dalla risposta.
   *
   * @return array|null
   *   I dati, o NULL se c'è un errore.
   */
  public function getData(): ?array {
    if ($this->hasError()) {
      return NULL;
    }
    
    return $this->getContent();
  }
}

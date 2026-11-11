<?php

namespace App\Twig;

use App\Service\Panier;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Rend disponible dans les gabarits Twig : {{ panier_nombre() }} (badge du menu). */
class PanierExtension extends AbstractExtension
{
    public function __construct(private readonly Panier $panier)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('panier_nombre', $this->panier->getNombreArticles(...)),
        ];
    }
}

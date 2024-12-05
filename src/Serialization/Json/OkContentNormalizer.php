<?php

declare(strict_types=1);

namespace SwagIndustries\Melodiia\Serialization\Json;

use Pagerfanta\Pagerfanta;
use SwagIndustries\Melodiia\Response\Model\Collection;
use SwagIndustries\Melodiia\Response\OkContent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * Class OkContentNormalizer.
 *
 * Normalize only the content of OkContent object using the serialization
 * context contained in OkContent object.
 */
class OkContentNormalizer implements NormalizerInterface, SerializerAwareInterface
{
    use SerializerAwareTrait;

    /** @var RequestStack */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param OkContent $object
     * @param string    $format
     *
     * @return array|bool|float|int|string
     */
    public function normalize($object, $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        $groups = $object->getSerializationContext()->getGroups();
        if (!empty($context['groups'])) {
            $groups = array_merge($context['groups'], $groups);
        }

        if (!empty($groups)) {
            $context['groups'] = $groups;
        }

        // Simple object case
        if (!$object->isCollection()) {
            return $this->serializer->normalize($object->getContent(), $format, $context);
        }

        // Collection case
        $content = $object->getContent();
        $result = [];

        // Pagination case
        if ($content instanceof Pagerfanta) {
            $result['meta'] = [
                'totalPages' => $content->getNbPages(),
                'totalResults' => $content->getNbResults(),
                'currentPage' => $content->getCurrentPage(),
                'maxPerPage' => $content->getMaxPerPage(),
            ];
            $uri = $this->requestStack->getMainRequest()->getUri();
            $previousPage = null;
            $nextPage = null;

            $hasPageParameter = str_contains($uri, 'page=');
            $prefix = $uri . (str_contains($uri, '?') ? '&' : '?') . 'page=';

            if ($content->hasPreviousPage()) {
                $previousPage = \preg_replace('/([?&])page=(\d+)/', '$1page=' . $content->getPreviousPage(), $uri);
            }
            if ($content->hasNextPage()) {
                $nextPage = $hasPageParameter
                    ? preg_replace('/([?&])page=(\d+)/', '$1page=' . $content->getNextPage(), $uri)
                    : $prefix . $content->getNextPage();
            }

            $result['links'] = [
                'prev' => $previousPage,
                'next' => $nextPage,
                'last' => $hasPageParameter && 1 !== $content->getNbPages() ? \preg_replace('/([?&])page=(\d+)/', '$1page=' . $content->getNbPages(), $uri) : $prefix . $content->getNbPages(),
                'first' => $hasPageParameter && 1 !== $content->getNbPages() ? \preg_replace('/([?&])page=(\d+)/', '$1page=1', $uri) : $prefix . 1,
            ];
        }
        if ($content instanceof Collection) {
            $uri = $this->requestStack->getMainRequest()->getUri();
            $result['meta'] = [
                'totalPages' => 1,
                'totalResults' => count($content),
                'currentPage' => 1,
                'maxPerPage' => count($content),
            ];
            $result['links'] = [
                'prev' => null,
                'next' => null,
                'last' => $uri,
                'first' => $uri,
            ];
        }

        $result['data'] = [];
        foreach ($content as $item) {
            $result['data'][] = $this->serializer->normalize($item, $format, $context);
        }

        return $result;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return is_object($data) && $data instanceof OkContent;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            OkContent::class => true,
        ];
    }
}

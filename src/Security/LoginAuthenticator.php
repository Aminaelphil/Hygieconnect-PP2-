<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Entity\Demande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private UrlGeneratorInterface $urlGenerator;
    private UserRepository $userRepository;
    private EntityManagerInterface $em;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->userRepository = $userRepository;
        $this->em = $em;
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');

        // Sauvegarde du dernier email pour pré-remplir le formulaire
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email, function (string $userIdentifier): ?UserInterface {
                $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Utilisateur introuvable.');
                }

                return $user;
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
            ]
        );
    }

public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
{
    $session = $request->getSession();
    $user = $token->getUser();

    // Vérifie si une demande est en attente dans la session
    $wizardData = $session->get('demande_wizard', []);
    if (!empty($wizardData['demande_id'])) {
        $demande = $this->em->getRepository(Demande::class)->find($wizardData['demande_id']);

        if ($demande && !$demande->getUser()) {
            // Rattachement automatique
            $demande->setUser($user);
            $demande->setStatut(Demande::STATUT_EN_COURS);

            $this->em->persist($demande);
            $this->em->flush();

            // Nettoyage session pour éviter le doublon
            unset($wizardData['demande_id']);
            $session->set('demande_wizard', $wizardData);

            // ✅ Rediriger directement vers la confirmation
            return new RedirectResponse($this->urlGenerator->generate('app_demande_confirmer'));
        }
    }

    // ➡️ Si aucune demande à confirmer, comportement normal
    // (ne touche rien ici)
    if ($targetPath = $this->getTargetPath($session, $firewallName)) {
        return new RedirectResponse($targetPath);
    }

    // Sinon retour à l’accueil par défaut
    return new RedirectResponse($this->urlGenerator->generate('app_home'));
}

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Panier;
use App\Repository\UserRepository;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use App\Security\UserAuthenticator;
use Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    private $emailVerifier;

    public function __construct(EmailVerifier $emailVerifier)
    {
        $this->emailVerifier = $emailVerifier;
    }

    /**
     * @Route("/inscription", name="app_register")
     */
    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder, GuardAuthenticatorHandler $guardHandler, UserAuthenticator $authenticator, \Swift_Mailer $mailer): Response
    {
        $user = new User();
        // $panier = new Panier();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $passwordEncoder->encodePassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            //génération token d'activation
            $user->setActivationToken(md5(uniqid()));


            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            // do anything else you need here, like send an email
            $message = (new \Swift_Message('activation du compte'))
                // expediteur (moi)
                ->setFrom('geekbeezproject@gmail.com')
                // destinataire
                ->setTo($user->getEmail())
                // contenu
                ->setBody(
                    $this->renderView(
                        'registration/confirmation_email.html.twig', ['token' => $user->getActivationToken()]
                    ),
                    'text/html'
                )
            ;

            // on envoie l'email
            $mailer->send($message);

            return $guardHandler->authenticateUserAndHandleSuccess(
                $user,
                $request,
                $authenticator,
                'main' // firewall name in security.yaml
            );
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/activation/{token}", name="activation")
     */
    public function activation($token, UserRepository $userRepo)
    {
        // verif unique token
        $user = $userRepo ->findOneBy(['activation_token' => $token]);

        // if token doesnt exist
        if(!$user){
            throw $this->createNotFoundException("Utilisateur inconnu");
        }

        // else delete token
        $user->setActivationToken(null);
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        // send flash msg
        $this->addFlash('message', 'Vous avez bien activé votre compte.');

        // goto home page
        return $this->redirectToRoute('accueil');
        
    }
}

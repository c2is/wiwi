<?php

namespace Cungfoo\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Process\Process;

class FeatureTestController implements ControllerProviderInterface
{
    /**
     * Returns routes to connect to the given application.
     *
     * @param Application $app An Application instance
     *
     * @return ControllerCollection A ControllerCollection instance
     */
    public function connect(Application $app) {
        $ctl = $app['controllers_factory'];


        $ctl->match('/', function (Request $request) use ($app) {
            // some default data for when the form is displayed the first time
            $form = $app['form.factory']->createBuilder('form')
                ->add('url')
                ->getForm()
            ;
            $trace = "";
            $pass = true;
            $data = "";

            if ('POST' == $request->getMethod()) {
                $form->bind($request);

                if ($form->isValid()) {

                    $data = $form->getData();

                }

            }

            $urlToCheck = (is_array($data))? $data['url']:"";
            // display the form
            return $app->render('form.html.twig', array(
                'form'  => $form->createView(),
                'trace' => $trace,
                'pass'  => $pass,
                'urlToCheck'  => $urlToCheck,
            ));
        });

        $ctl->match('/iframe', function (Request $request) use ($app) {


            $urlToCheck = $request->query->get('urlToCheck');

            $stream = function () use($urlToCheck, $request){
                echo "<html>";
                echo '<link href="'.$request->getBasePath().'/assets/css/custom.css" rel="stylesheet">';
                echo '<script src="'.$request->getBasePath().'/assets/theme-backend/js/scripts.js"></script>';
                echo '<script src="'.$request->getBasePath().'/assets/js/custom.js"></script>';
                echo "<pre id='stdout'  class=''>";
                echo '<script language="JavaScript">
                $(window.parent.document).ready(function() {
                    $("body").animate({ scrollTop: $(document).height() }, 7000);
                });
                </script>';
                echo "<body>";
                flush();
                $process = new Process('export BEHAT_PARAMS="context[parameters][base_url]='.$urlToCheck.'";cd ../tests/functionals/;../../bin/behat');
                $process->run(function ($type, $buffer) {
                    if ('err' === $type) {
                        echo $buffer;
                    } else {
                        echo $buffer;
                        flush();
                    }
                });
                echo "</pre>";
                if ($process->getExitCode() == 1) {
                    echo '<script language="JavaScript">$("#stdout").attr("class", "failed");</script>';
                    flush();
                }
                else {
                    echo '<script language="JavaScript">$("#stdout").attr("class", "pass");</script>';
                    flush();
                }
                echo "</body></html>";
                flush();
            };
            return $app->stream($stream);
        });
        return $ctl;
    }
}

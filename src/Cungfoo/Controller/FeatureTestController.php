<?php

namespace Cungfoo\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Constraints as Assert;

class FeatureTestController implements ControllerProviderInterface
{
    /**
     * Returns routes to connect to the given application.
     *
     * @param Application $app An Application instance
     *
     * @return ControllerCollection A ControllerCollection instance
     */
    public function connect(Application $app)
    {
        $ctl = $app['controllers_factory'];


        $ctl->match('/', function (Request $request) use ($app) {
            // some default data for when the form is displayed the first time
            $form = $app['form.factory']->createBuilder('form')
                ->add('url', 'text', array(
                    'constraints' => array(new Assert\Url())))
                ->add('robots','checkbox',  array("label"=>"Robots and metas","required"=>false, "disabled"=>true))
                ->add('pagesStatus','checkbox',  array("label"=>"No 404","required"=>false))
                ->getForm()
            ;
            $trace = "";
            $pass = true;
            $data = "";
            $formErrors = "";
            if ('POST' == $request->getMethod()) {
                $form->bind($request);
                if ($form->isValid()) {
                    $data = $form->getData();
                }
                else {
                    $formErrors = "Bad value given";
                }
            }
            $urlToCheck = "";
            $features = array("robots");
            if(is_array($data)) {
                $urlToCheck = $data['url'];
                if($data['pagesStatus']) {
                    $features[] = "pagesStatus";
                }
            }
            // display the form
            return $app->render('form.html.twig', array(
                'form'  => $form->createView(),
                'trace' => $trace,
                'pass'  => $pass,
                'urlToCheck'  => $urlToCheck,
                'features'  => implode("/",$features),
                'feature404On'  => in_array("pagesStatus",$features),
                'formSent' => ('POST' == $request->getMethod()),
                'formErrors' => $formErrors,
            ));
        });

        $ctl->match('/iframe/{protocol}://{urlToCheck}/{feature1}/{feature2}', function (Request $request, $protocol, $urlToCheck, $feature1, $feature2) use ($app){

            // secure : get only url, no get's parameters
            $urlToCheck = $protocol."://".parse_url($protocol."://".$urlToCheck, PHP_URL_HOST);

            $features = array();
            ($feature1 != "")? $features[] = "features/".$feature1.".feature":"";
            ($feature2 != "")? $features[] = "features/".$feature2.".feature":"";

            $stream = function () use($urlToCheck, $request, $features){
                echo "<!DOCTYPE html>";
                echo "<html>";
                echo "<head>";
                echo '<link href="'.$request->getBasePath().'/assets/css/custom.css" rel="stylesheet">';
                echo '<script src="'.$request->getBasePath().'/assets/theme-backend/js/scripts.js"></script>';
                echo '<script src="'.$request->getBasePath().'/assets/js/custom.js"></script>';
                echo '<script language="JavaScript">
                $(window.parent.document).ready(function() {
                    $("body").animate({ scrollTop: $(document).height() }, 7000);
                });
                </script>';
                echo "<head>";
                echo "<body>";
                echo "<pre id='stdout'  class=''>";
                flush();
                $procTimeOut = 3600;
                $exitCode = 0;
                foreach ($features as $feature) {
                    $process = $this->runProcess('export BEHAT_PARAMS="context[parameters][base_url]='.$urlToCheck.'";cd ../tests/functionals/;../../bin/behat '.$feature,null, null, null,$procTimeOut);
                    if ($process->getExitCode() != 0) {
                        $exitCode = 1;
                    }
                }


                echo "</pre>";
                if ($exitCode == 0) {
                    echo '<script language="JavaScript">$("#stdout").attr("class", "pass");</script>';
                    flush();
                }
                else {
                    echo '<script language="JavaScript">$("#stdout").attr("class", "failed");</script>';
                    flush();
                }
                echo "</body></html>";
                flush();
            };
            return $app->stream($stream);
        })
            ->value("feature1", null)
            ->value("feature2", null);
        return $ctl;
    }
    private function runProcess($process, $timout)
    {
        $process = new Process($process,null, null, null,$timout);
        $process->run(function ($type, $buffer) {
            if ('err' === $type) {
                echo $buffer;
            } else {
                echo $buffer;
                flush();
            }
        });

        return $process;
    }
}

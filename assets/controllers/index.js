import { Application } from "@hotwired/stimulus";
import AuthController from "./auth_controller.js";
import LanguageSwitcherController from "./language_switcher_controller.js";
import Progress_controller from "./progress_controller.js";
import AssignController  from "./company/assign_controller.js";
import CompanyCertificatesController from "./company/certificates_controller.js";





const application = Application.start();    
application.register("auth", AuthController);
application.register("language-switcher", LanguageSwitcherController);
application.register("progress", Progress_controller);
application.register('assign', AssignController);
application.register("company-certificates", CompanyCertificatesController);





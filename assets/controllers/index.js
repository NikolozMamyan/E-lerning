import { Application } from "@hotwired/stimulus";
import AuthController from "./auth_controller.js";
import Progress_controller from "./progress_controller.js";





const application = Application.start();
application.register("auth", AuthController);
application.register("progress", Progress_controller);





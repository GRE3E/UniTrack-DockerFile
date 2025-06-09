import { Component, OnInit } from '@angular/core';
import { UserService } from '../services/user.service';
import { Router } from '@angular/router';
import { NavController } from '@ionic/angular';
import { EnvioCorreoService } from '../services/envio-correo.service';

@Component({
  selector: 'app-correo',
  templateUrl: './correo.page.html',
  styleUrls: ['./correo.page.scss'],
})
export class CorreoPage implements OnInit {
email: string = ''; 
  constructor(private userService: EnvioCorreoService, private router: Router, private navCtrl: NavController) { }

  ngOnInit() {
  }

  sendVerificationCode() {
    this.userService.sendVerificationCode(this.email).subscribe(
      response => {
        this.navCtrl.navigateForward('/verificar');
      },
      error => {
        console.error(error);
        // Manejo de errores
      }
    );
  }

}

import { Component, OnInit } from '@angular/core';
import { UserService } from 'src/app/services/user.service';
import { NavController } from '@ionic/angular';
import { EnvioCorreoService } from '../services/envio-correo.service';

@Component({
  selector: 'app-contrasena',
  templateUrl: './contrasena.page.html',
  styleUrls: ['./contrasena.page.scss'],
})
export class ContrasenaPage implements OnInit {
  newPassword: string = '';

  constructor(private userService: EnvioCorreoService, private navCtrl: NavController) {}

  resetPassword() {
    this.userService.resetPassword(this.newPassword, this.userService.currentUser.code).subscribe(
      response => {
        this.navCtrl.navigateRoot('/login');
      },
      error => {
        console.error(error);
        // Manejo de errores
      }
    );
  }
  ngOnInit() {
  }
}

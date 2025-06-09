import { ComponentFixture, TestBed } from '@angular/core/testing';
import { SalidasPage } from './salidas.page';

describe('SalidasPage', () => {
  let component: SalidasPage;
  let fixture: ComponentFixture<SalidasPage>;

  beforeEach(() => {
    fixture = TestBed.createComponent(SalidasPage);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
